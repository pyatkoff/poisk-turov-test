"""Bounded MATCH registration for the existing stock executor entrypoint.

No new transport or credential mechanism: checked_event and execute stay in core.
All old modes delegate unchanged. Native110 stages have fixed intake scopes.
"""
from __future__ import annotations

import ast
import os
import re

MODE = 'match-primary-candidate'
READBACK_MODE = 'match-primary-proof-readback'
NATIVE_MODE = 'match-native110-current'
NATIVE_OPERATION = 'int-andromeda-match-native-current-20261001-v1'
NATIVE_BATCH = 'native110-20260928'
GUARDED_MODE = 'match-native110-write'
GUARDED_OPERATION = 'int-andromeda-match-native110-write-20261001-v1'
GUARDED_INPUT_SHA = '59cfe4bf4001636f77a0e8ad440475c4d6b514599c9147fc984daad5f4f7849e'
BG_MODE = 'match-native110-bg-evidence'
BG_OPERATION = 'int-andromeda-match-native110-bg-evidence-20261001-v1'
SHAMS_GEO_MODE = 'match-shams-geo-evidence'
SHAMS_GEO_OPERATION = 'int-andromeda-match-shams-geo-evidence-20261001-v1'
SHAMS_GEO_READBACK_MODE = 'match-shams-geo-readback'
SHAMS_GEO_READBACK_OPERATION = 'int-andromeda-match-shams-geo-readback-20261001-v1'
SHAMS_WRITE_MODE = 'match-shams-current-write'
SHAMS_WRITE_OPERATION = 'int-andromeda-match-shams-current-write-20261001-v1'
SHAMS_WRITE_BATCH = 'shams9501-geo-20261001'
TARGET_MODE = 'match-tv-live30-target-catalog'
TARGET_OPERATION = 'int-andromeda-match-live30-target-catalog-20261001-v1'
TARGET_BATCH = 'tv-live30-targets-20261001'
TARGET_READBACK_MODE = 'match-tv-live30-target-readback'
TARGET_READBACK_OPERATION = 'int-andromeda-match-live30-target-readback-20261001-v1'
TARGET_PREFLIGHT_MODE = 'match-tv-live30-target-preflight'
TARGET_PREFLIGHT_OPERATION = 'int-andromeda-match-live30-target-preflight-20261001-v1'
TARGET_PREFLIGHT_BATCH = 'tv-live30-target-preflight-20261001'
TARGET_PREFLIGHT_READBACK_MODE = 'match-tv-live30-target-preflight-readback'
TARGET_PREFLIGHT_READBACK_OPERATION = 'int-andromeda-match-live30-target-preflight-readback-20261001-v1'
TARGET_V2_MODE = 'match-tv-live30-target-catalog-v2'
TARGET_V2_OPERATION = 'int-andromeda-match-live30-target-catalog-v2-20261001-v1'
TARGET_V2_BATCH = 'tv-live30-targets-v2-20261001'
SOURCE3_MODE = 'match-source3-native-current'
SOURCE3_OPERATION = 'int-andromeda-match-source3-native-current-20261001-v1'
SOURCE3_BATCH = 'source3-native-20261001'
SOURCE3_MANIFEST_SHA = '8af3a42bc63fb7b7eacb01df661cbf7ba6bcc83bdf9681adfc1e59c159b2cf85'
INTOURIST4_MODE = 'match-intourist4-selectors-readonly'
INTOURIST4_OPERATION = 'int-tourvisor-match-intourist4-selectors-readonly-20261001-v1'
INTOURIST4_BATCH = 'intourist4-official-context-20261001'
INTOURIST4_MANIFEST_SHA = '3f05ddb13707866e8b3442da61528a1713b0ac710b53840a8b43ef5181337778'
INTOURIST4_READBACK_MODE = 'match-intourist4-selectors-readback'
INTOURIST4_READBACK_OPERATION = 'int-tourvisor-match-intourist4-selectors-readback-20261004-v1'
INTOURIST4_READBACK_BATCH = 'intourist4-terminal-readback-20261004'
FUNSUN2_MODE = 'match-funsun2-selectors-readonly'
FUNSUN2_OPERATION = 'int-tourvisor-match-funsun2-selectors-readonly-20261001-v1'
FUNSUN2_BATCH = 'funsun2-mass83-20261001'
FUNSUN2_MANIFEST_SHA = '093970c2b59b95dc59f1187cd05dd50a3653d6cdd93ad892e5d5ead1685bebae'
ANEX2_MODE = 'match-anex2-selectors-readonly'
ANEX2_OPERATION = 'int-tourvisor-match-anex2-selectors-readonly-20261001-v1'
ANEX2_BATCH = 'anex2-mass83-20261001'
ANEX2_MANIFEST_SHA = '7bf2cd6dc73f451593308d3c2e78659a98725f5854c93680fcc6ea3f94b4f0f7'
BP8_MODE = 'match-bg8-pin-bindings-readonly'
BP8_OPERATION = 'int-andromeda-match-bg8-pin-bindings-readonly-20261004-v1'
BP8_BATCH = 'bg8-pin-bindings-20261004'
BP8_MANIFEST_SHA = '4a14f5b4c641d80e378e1358341da09de17dc35478087bff134f63879c14ec1c'
BP8_SOURCE_FILES = (
    'scripts/diagnostics/hotel_match_bg8_pin_bindings_readonly_v1.py',
    'scripts/diagnostics/fixtures/hotel_match_bg8_pin_bindings_readonly_v1.json',
)

NF7_MODE = 'match-nonbg7-unexported-fields-readonly'
NF7_OPERATION = 'int-andromeda-match-nonbg7-unexported-fields-20261004-v1'
NF7_BATCH = 'nonbg7-unexported-fields-20261004'
NF7_MANIFEST_SHA = '220cfc26cab422113d2caf6ce61080e8020a0fb5f9f2548916e828fa3cad43be'
NF7_SOURCE_FILES = (
    'scripts/diagnostics/hotel_match_nonbg7_unexported_fields_readonly_v1.py',
    'scripts/diagnostics/fixtures/hotel_match_nonbg7_unexported_fields_readonly_v1.json',
)

NU5_MODE = 'match-nonbg5-retained-url-paths-readonly'
NU5_OPERATION = 'int-andromeda-match-nonbg5-retained-url-paths-20261004-v1'
NU5_BATCH = 'nonbg5-retained-url-paths-20261004'
NU5_MANIFEST_SHA = 'a24dd81ad112bc220fda3721bfa98985460dc08e74ac6fadc9bb596e188fb269'
NU5_SOURCE_FILES = (
    'scripts/diagnostics/hotel_match_nonbg5_retained_url_paths_readonly_v1.py',
    'scripts/diagnostics/fixtures/hotel_match_nonbg5_retained_url_paths_readonly_v1.json',
)

NR5_MODE = 'match-nonbg5-url-paths-terminal-readback'
NR5_OPERATION = 'int-andromeda-match-nonbg5-url-paths-terminal-readback-20261004-v1'
NR5_BATCH = 'nonbg5-url-paths-terminal-readback-20261004'
NR5_MANIFEST_SHA = '515ccfc283244713f6ecd3b87c3bc5829e1173d9468a66e24d2fa54379950151'
NR5_SOURCE_FILES = (
    'scripts/diagnostics/hotel_match_nonbg5_url_paths_terminal_readback_v1.py',
    'scripts/diagnostics/fixtures/hotel_match_nonbg5_url_paths_terminal_readback_v1.json',
    *NU5_SOURCE_FILES,
)

NA3_MODE = 'match-native-absent3-retained-fields-readonly'
NA3_OPERATION = 'int-andromeda-match-native-absent3-retained-fields-20261008-v1'
NA3_BATCH = 'native-absent3-retained-fields-20261008'
NA3_SOURCE_FILES = ('scripts/diagnostics/hotel_match_native_absent3_retained_fields_readonly_v1.py', 'scripts/diagnostics/fixtures/hotel_match_native_absent3_retained_fields_readonly_v1.json', 'scripts/diagnostics/hotel_match_operator115_only2_retained_fields_readonly_v1.py', 'scripts/diagnostics/hotel_match_nonbg7_unexported_fields_readonly_v1.py', 'scripts/diagnostics/hotel_match_bg5_unexported_fields_readonly_v1.py')

URL3_MODE = 'match-native-absent3-saved-urls-readonly'
URL3_OPERATION = 'int-andromeda-match-native-absent3-saved-urls-20261008-v1'
URL3_BATCH = 'native-absent3-saved-urls-20261008'
URL3_SOURCE_FILES = (*NA3_SOURCE_FILES, 'scripts/diagnostics/hotel_match_nonbg5_retained_url_paths_readonly_v1.py')

OP1152_MODE = 'match-operator115-only2-retained-fields-readonly'
OP1152_OPERATION = 'int-andromeda-match-operator115-only2-retained-fields-20261007-v1'
OP1152_BATCH = 'operator115-only2-retained-20261007'
OP1152_SOURCE_FILES = (
    'scripts/diagnostics/hotel_match_operator115_only2_retained_fields_readonly_v1.py',
    'scripts/diagnostics/fixtures/hotel_match_operator115_only2_retained_fields_readonly_v1.json',
    'scripts/diagnostics/hotel_match_nonbg7_unexported_fields_readonly_v1.py',
    'scripts/diagnostics/hotel_match_bg5_unexported_fields_readonly_v1.py',
)

ALIAS3_MODE = 'match-alias3-retained-fields-readonly'
ALIAS3_OPERATION = 'int-andromeda-match-alias3-retained-fields-20261007-v1'
ALIAS3_BATCH = 'alias3-retained-fields-20261007'
ALIAS3_METADATA_MODE = 'match-alias3-terminal-metadata-readback'
ALIAS3_METADATA_OPERATION = 'int-andromeda-match-alias3-terminal-metadata-20261007-v1'
ALIAS3_METADATA_BATCH = 'alias3-terminal-metadata-20261007'
ALIAS3_SOURCE_FILES = (
    'scripts/diagnostics/hotel_match_alias3_retained_fields_readonly_v1.py',
    'scripts/diagnostics/fixtures/hotel_match_alias3_retained_fields_readonly_v1.json',
    'scripts/diagnostics/hotel_match_nonbg7_unexported_fields_readonly_v1.py',
    'scripts/diagnostics/hotel_match_bg5_unexported_fields_readonly_v1.py',
)

PASSIVE_OCT4_MODE = 'match-passive-oct4-frontier'
PASSIVE_OCT4_OPERATION = 'int-andromeda-match-passive-oct4-frontier-20261005-v1'
PASSIVE_OCT4_BATCH = 'passive-oct4-before175942'
PASSIVE_OCT4_SOURCE_FILES = (
    'scripts/diagnostics/hotel_match_passive_oct4_frontier_readonly_v1.py',
    'scripts/diagnostics/fixtures/hotel_match_passive_oct4_frontier_readonly_v1.json',
    'tests/hotel_match_passive_oct4_frontier_readonly_v1_test.py',
)

OBSERVED_PAGE1_MODE = 'match-observed-page1-identity'
OBSERVED_PAGE1_OPERATION = 'int-andromeda-match-observed-page1-identity-20261005-v1'
OBSERVED_PAGE1_BATCH = 'observed-page1-20261004-175945'
OBSERVED_PAGE1_SOURCE_FILES = (
    'scripts/diagnostics/hotel_match_observed_page1_identity_readonly_v1.py',
    'scripts/diagnostics/fixtures/hotel_match_observed_page1_identity_readonly_v1.json',
    'tests/hotel_match_observed_page1_identity_readonly_v1_test.py',
)

BF5_MODE = 'match-bg5-unexported-fields-readonly'
BF5_OPERATION = 'int-andromeda-match-bg5-unexported-fields-20261004-v1'
BF5_BATCH = 'bg5-unexported-fields-20261004'
BF5_MANIFEST_SHA = '715a8e88de3cbc1662e8319dce3cb13cd75dce1a11b8c2f199049ddaba676d5c'
BF5_SOURCE_FILES = (
    'scripts/diagnostics/hotel_match_bg5_unexported_fields_readonly_v1.py',
    'scripts/diagnostics/fixtures/hotel_match_bg5_unexported_fields_readonly_v1.json',
)

BF8_MODE = 'match-bg8-unexported-fields-readonly'
BF8_OPERATION = 'int-andromeda-match-bg8-unexported-fields-20261004-v1'
BF8_BATCH = 'bg8-unexported-fields-20261004'
BF8_MANIFEST_SHA = '8f51faf57a29343f3989c58315b939e10295e93befb5da8d6056763237e21207'
BF8_SOURCE_FILES = (
    'scripts/diagnostics/hotel_match_bg8_unexported_fields_readonly_v1.py',
    'scripts/diagnostics/fixtures/hotel_match_bg8_unexported_fields_readonly_v1.json',
)

DELTA_MODE = 'match-user-search-delta-readonly'
DELTA_OPERATION = 'int-andromeda-match-user-search-delta-20261004-v1'
DELTA_BATCH = 'user-search-delta-20261004'
DELTA_MANIFEST_SHA = 'a58f13ec8513a612e5ae93302c81491ac132079b0f654ae72344385e6edf8057'
DELTA_SOURCE_FILES = (
    'scripts/diagnostics/hotel_match_user_search_delta_readonly_v1.py',
    'scripts/diagnostics/fixtures/hotel_match_user_search_delta_readonly_v1.json',
)
TARGET_SOURCE_FILES = (
    'scripts/diagnostics/hotel_match_pending8_transition_v76.php',
    'scripts/diagnostics/hotel_match_tv_live30_target_catalog_v1.php',
)
TARGET_PREFLIGHT_SOURCE_FILES = (
    'scripts/diagnostics/hotel_match_pending8_transition_v76.php',
    'scripts/diagnostics/hotel_match_tv_live30_target_preflight_v1.php',
)
TARGET_V2_SOURCE_FILES = (
    'scripts/diagnostics/hotel_match_pending8_transition_v76.php',
    'scripts/diagnostics/hotel_match_tv_live30_target_catalog_v1.php',
    'scripts/diagnostics/hotel_match_tv_live30_target_catalog_v2.php',
)
BG_EXPECTED = {'13293': (367, '610184500', '102610184500'), '60328': (9242, '625162113', '102625162113'),
    '205729': (9283, '625414997', '102625414997'), '2000041008': (62868, '610121438', '102610121438'),
    '2000052316': (70457, '610144591', '102610144591'), '2000059209': (67000, '610155352', '102610155352'),
    '2000060910': (72889, '610175943', '102610175943'), '2000062548': (72865, '610149698', '102610149698'),
    '2000062557': (75791, '610179507', '102610179507'), '2000071830': (15902, '610210401', '102610210401'),
    '2000081107': (83106, '610227700', '102610227700'), '2000086021': (316, '668981793', '102668981793'),
    '2000086129': (75538, '610160139', '102610160139'), '2000087863': (99582, '610222069', '102610222069'),
    '2000041090': (1572, '625076488', '102625076488'), '2000072753': (1267, '610197738', '102610197738'),
    '2000072804': (65770, '610175325', '102610175325'), '2000079689': (1175, '610214047', '102610214047')}
BATCH = 'samo3-20260929'
OPERATION_RE = re.compile(r'\Aint-andromeda-match-primary-[a-z0-9-]{8,48}-v[1-9][0-9]*\Z')
SOURCE_FILES = (
    'scripts/diagnostics/hotel_match_pending8_transition_v76.php',
    'scripts/diagnostics/hotel_match_raw_native_pending11_v78.php',
    'scripts/diagnostics/hotel_match_primary_candidate_v1.php',
)
PROOF_SOURCE_FILES = SOURCE_FILES + ('scripts/diagnostics/hotel_match_primary_proof_audit_v1.php',)
NATIVE_SOURCE_FILES = PROOF_SOURCE_FILES + (
    'scripts/diagnostics/hotel_match_native110_current_v1.php',
    'scripts/diagnostics/fixtures/hotel_match_native110_current_v1.json',
)
GUARDED_SOURCE_FILES = NATIVE_SOURCE_FILES + ('scripts/diagnostics/hotel_match_native110_guarded_v1.php',)
BG_SOURCE_FILES = NATIVE_SOURCE_FILES + ('scripts/diagnostics/hotel_match_native110_bg_evidence_v1.php',)
SHAMS_GEO_SOURCE_FILES = NATIVE_SOURCE_FILES + ('scripts/diagnostics/hotel_match_shams_geography_saved_v1.php',)
SHAMS_WRITE_SOURCE_FILES = tuple(dict.fromkeys(GUARDED_SOURCE_FILES + SHAMS_GEO_SOURCE_FILES +
    ('scripts/diagnostics/hotel_match_shams_guarded_v1.php',)))
SOURCE3_SOURCE_FILES = PROOF_SOURCE_FILES + (
    'scripts/diagnostics/hotel_match_source3_native_current_v1.php',
    'scripts/diagnostics/fixtures/hotel_match_source3_native_current_v1.json',
)
INTOURIST4_SOURCE_FILES = (
    'scripts/diagnostics/hotel_match_intourist4_selectors_readonly_v1.py',
    'scripts/diagnostics/fixtures/hotel_match_intourist4_selectors_readonly_v1.json',
    'reports/hotel-match-intourist-official-context-20261001.json',
    'reports/hotel-match-minimal-nonbg-ledger-reconcile-20261001.json',
)
FUNSUN2_SOURCE_FILES = (
    'scripts/diagnostics/hotel_match_funsun2_selectors_readonly_v1.py',
    'scripts/diagnostics/fixtures/hotel_match_funsun2_selectors_readonly_v1.json',
    'reports/hotel-match-mass83-proof-minimization-20261001.json',
    'reports/hotel-match-minimal-nonbg-ledger-reconcile-20261001.json',
)

ANEX2_SOURCE_FILES = (
    'scripts/diagnostics/hotel_match_anex2_selectors_readonly_v1.py',
    'scripts/diagnostics/fixtures/hotel_match_anex2_selectors_readonly_v1.json',
    'reports/hotel-match-mass83-proof-minimization-20261001.json',
    'reports/hotel-match-minimal-nonbg-ledger-reconcile-20261001.json',
)


def register_parser(core) -> None:
    """Extend mode parsing; never replace owner/ref/comment authorization."""
    original = core.parse_command

    def parse(body: str) -> dict:
        if not body.startswith(core.PREFIX):
            return original(body)
        parts = body[len(core.PREFIX):].split()
        if len(parts) < 2 or parts[1] not in (MODE, READBACK_MODE, NATIVE_MODE, GUARDED_MODE, BG_MODE, SHAMS_GEO_MODE, SHAMS_GEO_READBACK_MODE, SHAMS_WRITE_MODE, TARGET_MODE, TARGET_READBACK_MODE, TARGET_PREFLIGHT_MODE, TARGET_PREFLIGHT_READBACK_MODE, TARGET_V2_MODE, SOURCE3_MODE, INTOURIST4_MODE, INTOURIST4_READBACK_MODE, FUNSUN2_MODE, ANEX2_MODE, DELTA_MODE, BF8_MODE, BP8_MODE, BF5_MODE, NF7_MODE, NU5_MODE, NR5_MODE, OBSERVED_PAGE1_MODE, PASSIVE_OCT4_MODE, ALIAS3_MODE, ALIAS3_METADATA_MODE, OP1152_MODE, NA3_MODE, URL3_MODE):
            return original(body)
        core.need(len(parts) == 4, 'primary_command_shape')
        source, mode, operation, batch = parts
        core.need(core.SHA_RE.fullmatch(source) is not None, 'source_sha')
        if mode == URL3_MODE:
            core.need(operation == URL3_OPERATION and batch == URL3_BATCH, 'native_absent3_saved_urls_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                    'batch': URL3_BATCH, 'maximum_writes': 0, 'provider_http_calls': 0}
        if mode == NA3_MODE:
            core.need(operation == NA3_OPERATION and batch == NA3_BATCH, 'native_absent3_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                    'batch': NA3_BATCH, 'maximum_writes': 0, 'provider_http_calls': 0}
        if mode == OP1152_MODE:
            core.need(operation == OP1152_OPERATION and batch == OP1152_BATCH, 'operator115_only2_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                    'batch': OP1152_BATCH, 'maximum_writes': 0, 'provider_http_calls': 0}
        if mode == ALIAS3_METADATA_MODE:
            core.need(operation == ALIAS3_METADATA_OPERATION and batch == ALIAS3_METADATA_BATCH, 'alias3_metadata_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                    'batch': ALIAS3_METADATA_BATCH, 'maximum_writes': 0, 'provider_http_calls': 0}
        if mode == ALIAS3_MODE:
            core.need(operation == ALIAS3_OPERATION and batch == ALIAS3_BATCH, 'alias3_fields_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                    'batch': ALIAS3_BATCH, 'maximum_writes': 0, 'provider_http_calls': 0}
        if mode == PASSIVE_OCT4_MODE:
            core.need(operation == PASSIVE_OCT4_OPERATION and batch == PASSIVE_OCT4_BATCH,
                      'passive_oct4_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': PASSIVE_OCT4_OPERATION,
                    'batch': PASSIVE_OCT4_BATCH, 'maximum_writes': 0, 'provider_http_calls': 0}
        if mode == OBSERVED_PAGE1_MODE:
            core.need(operation == OBSERVED_PAGE1_OPERATION and batch == OBSERVED_PAGE1_BATCH,
                      'observed_page1_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                    'batch': OBSERVED_PAGE1_BATCH, 'maximum_writes': 0, 'provider_http_calls': 0}
        if mode == BP8_MODE:
            core.need(operation == BP8_OPERATION and batch == BP8_BATCH, 'bg8_pins_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': BP8_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 0}
        if mode == NF7_MODE:
            core.need(operation == NF7_OPERATION and batch == NF7_BATCH, 'nonbg7_fields_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': NF7_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 0}
        if mode == NR5_MODE:
            core.need(operation == NR5_OPERATION and batch == NR5_BATCH, 'nonbg5_terminal_readback_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': NR5_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 0}
        if mode == NU5_MODE:
            core.need(operation == NU5_OPERATION and batch == NU5_BATCH, 'nonbg5_url_paths_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': NU5_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 0}
        if mode == BF5_MODE:
            core.need(operation == BF5_OPERATION and batch == BF5_BATCH, 'bg5_fields_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': BF5_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 0}
        if mode == BF8_MODE:
            core.need(operation == BF8_OPERATION and batch == BF8_BATCH, 'bg8_fields_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': BF8_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 0}
        if mode == DELTA_MODE:
            core.need(operation == DELTA_OPERATION and batch == DELTA_BATCH, 'delta_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': DELTA_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 0}
        if mode == ANEX2_MODE:
            core.need(operation == ANEX2_OPERATION and batch == ANEX2_BATCH, 'anex2_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': ANEX2_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 12}
        if mode == FUNSUN2_MODE:
            core.need(operation == FUNSUN2_OPERATION and batch == FUNSUN2_BATCH, 'funsun2_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': FUNSUN2_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 7}
        if mode == INTOURIST4_READBACK_MODE:
            core.need(operation == INTOURIST4_READBACK_OPERATION and batch == INTOURIST4_READBACK_BATCH, 'intourist4_readback_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': INTOURIST4_READBACK_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 0}
        if mode == INTOURIST4_MODE:
            core.need(operation == INTOURIST4_OPERATION and batch == INTOURIST4_BATCH, 'intourist4_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': INTOURIST4_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 14}
        if mode == SOURCE3_MODE:
            core.need(operation == SOURCE3_OPERATION and batch == SOURCE3_BATCH, 'source3_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': SOURCE3_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 3}
        if mode == TARGET_V2_MODE:
            core.need(operation == TARGET_V2_OPERATION and batch == TARGET_V2_BATCH, 'target_catalog_v2_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': TARGET_V2_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 0}
        if mode == TARGET_PREFLIGHT_READBACK_MODE:
            core.need(operation == TARGET_PREFLIGHT_READBACK_OPERATION and batch == TARGET_PREFLIGHT_BATCH, 'target_preflight_readback_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': TARGET_PREFLIGHT_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 0}
        if mode == TARGET_PREFLIGHT_MODE:
            core.need(operation == TARGET_PREFLIGHT_OPERATION and batch == TARGET_PREFLIGHT_BATCH, 'target_preflight_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': TARGET_PREFLIGHT_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 0}
        if mode == TARGET_READBACK_MODE:
            core.need(operation == TARGET_READBACK_OPERATION and batch == TARGET_BATCH, 'target_readback_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': TARGET_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 0}
        if mode == TARGET_MODE:
            core.need(operation == TARGET_OPERATION and batch == TARGET_BATCH, 'target_catalog_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': TARGET_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 0}
        if mode == SHAMS_WRITE_MODE:
            core.need(operation == SHAMS_WRITE_OPERATION and batch == SHAMS_WRITE_BATCH, 'shams_write_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': SHAMS_WRITE_BATCH,
                    'maximum_writes': 1, 'provider_http_calls': 0, 'input_sha256': GUARDED_INPUT_SHA,
                    'geography_operation': SHAMS_GEO_READBACK_OPERATION}
        if mode == SHAMS_GEO_READBACK_MODE:
            core.need(operation == SHAMS_GEO_READBACK_OPERATION and batch == NATIVE_BATCH, 'shams_geo_readback_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': NATIVE_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 0, 'input_sha256': GUARDED_INPUT_SHA}
        if mode == SHAMS_GEO_MODE:
            core.need(operation == SHAMS_GEO_OPERATION and batch == NATIVE_BATCH, 'shams_geo_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': NATIVE_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 0, 'input_sha256': GUARDED_INPUT_SHA}
        if mode == BG_MODE:
            core.need(operation == BG_OPERATION and batch == NATIVE_BATCH, 'bg_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': NATIVE_BATCH,
                    'maximum_writes': 0, 'provider_http_calls': 0, 'input_sha256': GUARDED_INPUT_SHA}
        if mode == GUARDED_MODE:
            core.need(operation == GUARDED_OPERATION and batch == NATIVE_BATCH, 'guarded_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation, 'batch': NATIVE_BATCH,
                    'maximum_writes': 4, 'provider_http_calls': 0, 'input_sha256': GUARDED_INPUT_SHA}
        if mode == NATIVE_MODE:
            core.need(operation == NATIVE_OPERATION and batch == NATIVE_BATCH, 'native110_fixed_scope')
            return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                    'batch': NATIVE_BATCH, 'maximum_writes': 0, 'provider_http_calls': 0}
        core.need(core.OP_RE.fullmatch(operation) is not None
                  and OPERATION_RE.fullmatch(operation) is not None, 'primary_operation')
        core.need(batch == BATCH, 'primary_batch')
        return {'source_sha': source, 'mode': mode, 'operation_id': operation,
                'batch': BATCH, 'maximum_writes': 0 if mode == READBACK_MODE else 3, 'provider_http_calls': 0}

    core.parse_command = parse


REMOTE_HANDLER = r'''
def run_match_primary_candidate(stage):
    if (payload.get('batch')!='samo3-20260929'
            or payload.get('maximum_writes')!=3 or payload.get('provider_http_calls')!=0
            or not re.fullmatch(r'int-andromeda-match-primary-[a-z0-9-]{8,48}-v[1-9][0-9]*',operation)):
        fail('primary_batch_scope')
    expected={('9501',420),('2000034238',16944),('3126',42903)}
    match_home=home/'.anytoour-match'
    match_root=match_home/'operations'
    for directory in (match_home,match_root):
        if directory.is_symlink() or directory.resolve()!=directory:
            fail('primary_private_root')
        directory.mkdir(mode=0o700,exist_ok=True)
    child=match_root/operation
    if child.exists() or child.is_symlink(): fail('primary_child_exists_no_replay')
    # A new operation name alone cannot rerun this approved first batch.
    batch_marker=match_home/'primary-batch-samo3-20260929.json'
    reservation={'operation':operation,'parent_operation':operation,'source_sha':source,
                 'batch':'samo3-20260929','maximum_writes':3,'provider_http_calls':0,
                 'state':'reserved_before_db','reserved_at':int(time.time())}
    def exclusive_json(path,value):
        data=json.dumps(value,sort_keys=True,separators=(',',':')).encode()+b'\n'
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as stream:
            stream.write(data);stream.flush();os.fsync(stream.fileno())
    exclusive_json(batch_marker,reservation)
    child.mkdir(mode=0o700)
    exclusive_json(child/'reservation.json',reservation)
    runner=stage/'scripts/diagnostics/hotel_match_primary_candidate_v1.php'
    if not safe_file(runner,2*1024*1024): fail('primary_runner_missing')
    # No supplier credentials or arbitrary environment is passed to this PHP mode.
    child_env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    child_env.update({'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(child),
                      'MATCH_SOURCE_SHA':source})
    run=subprocess.run(['php',str(runner),'--execute'],cwd=project,env=child_env,
                       capture_output=True,text=True,timeout=240)
    result_path=child/'result.json';receipt_path=child/'receipt.json'
    if not safe_file(result_path,2*1024*1024) or not safe_file(receipt_path,1048576):
        fail('primary_terminal_missing_no_replay')
    data=safe_json(result_path,2*1024*1024);receipt=safe_json(receipt_path,1048576)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    if (data.get('operation')!=operation or data.get('source_sha')!=source
            or data.get('batch')!='samo3-20260929' or data.get('requested_candidates')!=3
            or data.get('provider_http_calls')!=0 or data.get('no_replay') is not True
            or receipt.get('operation')!=operation or receipt.get('source_sha')!=source
            or receipt.get('batch')!='samo3-20260929' or receipt.get('result_sha256')!=digest
            or receipt.get('state')!=data.get('state') or receipt.get('provider_http_calls')!=0
            or receipt.get('no_replay') is not True):
        fail('primary_terminal_binding')
    count=data.get('mapping_writes')
    if count is not None and (type(count) is not int or not 0<=count<=3):
        fail('primary_write_count')
    if (data.get('database_writes')!=count or receipt.get('mapping_writes')!=count
            or receipt.get('database_writes')!=count
            or receipt.get('readback_verified')!=data.get('readback_verified')):
        fail('primary_terminal_count_binding')
    seen=set()
    for key in ('rows','held','already'):
        items=data.get(key,[])
        if not isinstance(items,list) or len(items)>3: fail('primary_terminal_row_shape')
        for row in items:
            if not isinstance(row,dict): fail('primary_terminal_row_shape')
            pair=(str(row.get('catalog_id')),row.get('local_hotel_id'))
            if pair not in expected or pair in seen: fail('primary_terminal_row_scope')
            seen.add(pair)
    successful=data.get('state') in ('committed_readback_verified','completed_no_new_writes')
    if successful:
        if (run.returncode!=0 or run.stderr.strip() or type(count) is not int
                or data.get('readback_verified') is not True
                or len(data.get('rows',[]))!=count
                or data.get('current_candidates_evaluated')!=3 or seen!=expected):
            fail('primary_success_contract')
        if data['state']=='committed_readback_verified' and (count<1
                or data.get('commit_completed') is not True
                or data.get('effective_resolver_verified') is not True):
            fail('primary_commit_contract')
        if data['state']=='completed_no_new_writes' and count!=0:
            fail('primary_zero_contract')
    elif data.get('readback_verified') is True:
        fail('primary_false_verification')
    return {'operation':operation,'result_sha256':digest,'successful':successful,
            'exit_code':run.returncode,'stderr_sha256':hashlib.sha256(run.stderr.encode()).hexdigest() if run.stderr else None,
            'summary':data}

'''

REMOTE_DISPATCH = r'''    if mode=='match-primary-candidate':
        primary=run_match_primary_candidate(stage)
        result['match_primary_candidate']=primary
        result['supplier_calls']=0
        result['database_writes']=primary['summary'].get('database_writes')
        result['mapping_writes']=primary['summary'].get('mapping_writes')
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete' if primary['successful'] else 'terminal_nonzero_no_replay'
'''


REMOTE_PROOF_HANDLER = r'''
def run_match_primary_proof_readback(stage):
    if (payload.get('batch')!='samo3-20260929' or payload.get('maximum_writes')!=0
            or payload.get('provider_http_calls')!=0
            or not re.fullmatch(r'int-andromeda-match-primary-[a-z0-9-]{8,48}-v[1-9][0-9]*',operation)):
        fail('primary_proof_scope')
    root=home/'.anytoour-match/operations'
    if not root.is_dir() or root.is_symlink() or root.resolve()!=root:
        fail('primary_proof_private_root')
    runner=stage/'scripts/diagnostics/hotel_match_primary_proof_audit_v1.php'
    if not safe_file(runner,2*1024*1024): fail('primary_proof_runner_missing')
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0',str(runner),'--read-saved',str(root)],
                       cwd=project,env=env,capture_output=True,text=True,timeout=90)
    if run.returncode!=0 or run.stderr.strip() or len(run.stdout.encode())>256*1024:
        fail('primary_proof_read_failed')
    data=json.loads(run.stdout)
    top_fields={'state','batch','rows','provider_http_calls','database_writes','mapping_writes','safe_to_write_now'}
    if (not isinstance(data,dict) or set(data) not in (top_fields,top_fields|{'origin_lookup'})
            or data.get('state')!='completed_saved_proof_audit' or data.get('batch')!='samo3-20260929'
            or any(type(data.get(k)) is not int or data[k]!=0 for k in ('provider_http_calls','database_writes','mapping_writes'))
            or data.get('safe_to_write_now') is not False): fail('primary_proof_authority')
    expected={
        420:('9501','operator_342','24402','hotel-match-residual2041-common4-nonanex-detail-1971-20260921-v4','2534eebc2a8b0ba79dbf32dedda165c7e87da609284b25c5209c04747624e564'),
        16944:('2000034238','operator_315','211585','hotel-match-residual2041-search30-common4-1971-20260921-v3','76c740c4efbb95a2c2c44fd7fe69ecea30b5091c0e17dfec2bb4cf30da75cea2'),
        42903:('3126','operator_315','849821','hotel-match-residual2041-common4-nonanex-detail-1971-20260921-v4','2534eebc2a8b0ba79dbf32dedda165c7e87da609284b25c5209c04747624e564'),
    }
    rows=data.get('rows')
    if not isinstance(rows,list) or len(rows)!=3: fail('primary_proof_rows')
    seen=set()
    fields={'tv_hotel_id','catalog_id','supplier_namespace','native_id','source_operation','source_result_sha256','safe_to_write_now','state','failures'}
    proof_fields={'proof_matches','source_targets','target_natives','edge_checks','invalid_edge_rows','edge_checks_omitted'}
    failure_names={'producer_edges_missing','verified_edge_missing','source_target_not_unique','target_native_not_unique',
        'retained_relative_path','retained_file','retained_read','retained_digest','retained_json','retained_terminal','salvaged_terminal_required','retained_read_failed'}
    edge_fields={'tv_hotel_id','operator_id','state','link_state','namespace','positive_native_candidates',
        'operator_link_sha256','tour_id_sha256','search_id_sha256','operator_link_host'}
    for row in rows:
        if not isinstance(row,dict): fail('primary_proof_rows')
        identity=row.get('tv_hotel_id')
        if type(identity) is not int or identity not in expected or identity in seen: fail('primary_proof_pair')
        seen.add(identity)
        if (tuple(row.get(k) for k in ('catalog_id','supplier_namespace','native_id','source_operation','source_result_sha256'))!=expected[identity]
                or row.get('safe_to_write_now') is not False): fail('primary_proof_pair')
        state=row.get('state'); failures=row.get('failures')
        if (state not in ('producer_unavailable','proof_hold','saved_tv_proof_verified')
                or not isinstance(failures,list) or any(f not in failure_names for f in failures)
                or (state=='saved_tv_proof_verified')!= (failures==[])): fail('primary_proof_state')
        if state=='producer_unavailable':
            if set(row)!=fields: fail('primary_proof_projection')
            continue
        # Missing edges has the smaller diagnostic schema; no raw field is permitted.
        if set(row) not in (fields|proof_fields,fields|{'proof_matches','source_targets','target_natives','edge_checks'}): fail('primary_proof_projection')
        for key in ('proof_matches','invalid_edge_rows','edge_checks_omitted'):
            if key in row and (type(row[key]) is not int or row[key]<0): fail('primary_proof_count')
        for key in ('source_targets','target_natives','edge_checks'):
            if not isinstance(row.get(key),list) or len(row[key])>1000: fail('primary_proof_projection')
        if any(type(i) is not int or i<=0 for i in row['source_targets']): fail('primary_proof_target')
        if any(not isinstance(n,str) or not re.fullmatch(r'[1-9][0-9]{0,31}',n) for n in row['target_natives']): fail('primary_proof_native')
        if len(row['edge_checks'])>100: fail('primary_proof_projection')
        for check in row['edge_checks']:
            if (not isinstance(check,dict) or set(check)!={'json_pointer','verified','failed_fields'}
                    or not isinstance(check['json_pointer'],str) or not re.fullmatch(r'/edges/[0-9]{1,8}',check['json_pointer'])
                    or type(check['verified']) is not bool or not isinstance(check['failed_fields'],list)
                    or any(f not in edge_fields for f in check['failed_fields'])): fail('primary_proof_projection')
    inventory=data.get('origin_lookup')
    if inventory is not None:
        if (not isinstance(inventory,dict) or set(inventory)!={'state','files_read','bytes_read','skipped_large_files','invalid_files','rows'}
                or inventory['state'] not in ('completed_bounded_inventory','partial_bounded_inventory')
                or any(type(inventory[k]) is not int or inventory[k]<0 for k in ('files_read','bytes_read','skipped_large_files','invalid_files'))
                or inventory['files_read']>5000 or inventory['bytes_read']>536870912
                or not isinstance(inventory['rows'],list) or len(inventory['rows'])!=3): fail('primary_proof_inventory')
        seen=set()
        for row in inventory['rows']:
            if (not isinstance(row,dict) or set(row)!={'tv_hotel_id','references'} or type(row['tv_hotel_id']) is not int
                    or row['tv_hotel_id'] not in expected or row['tv_hotel_id'] in seen
                    or not isinstance(row['references'],list) or len(row['references'])>100): fail('primary_proof_inventory')
            identity=row['tv_hotel_id'];seen.add(identity)
            operator=43 if identity==420 else 25
            for ref in row['references']:
                if (not isinstance(ref,dict) or set(ref)!={'source_operation','file','sha256','json_pointer','verified','failed_fields'}
                        or not isinstance(ref['source_operation'],str) or not re.fullmatch(r'hotel-match-[a-zA-Z0-9_-]{1,180}',ref['source_operation'])
                        or ref['file'] not in ('result.json','tv-edge-'+str(identity)+'-'+str(operator)+'.json')
                        or not isinstance(ref['sha256'],str) or not re.fullmatch(r'[a-f0-9]{64}',ref['sha256'])
                        or not isinstance(ref['json_pointer'],str)
                        or not (re.fullmatch(r'/edges/[0-9]{1,8}',ref['json_pointer']) if ref['file']=='result.json' else ref['json_pointer']=='')
                        or type(ref['verified']) is not bool or not isinstance(ref['failed_fields'],list)
                        or any(f not in edge_fields for f in ref['failed_fields'])): fail('primary_proof_inventory')
    return data

'''

REMOTE_PROOF_DISPATCH = r'''    if mode=='match-primary-proof-readback':
        result['match_primary_proof_readback']=run_match_primary_proof_readback(stage)
        result['supplier_calls']=0
        result['database_reads']=0
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''


REMOTE_NATIVE_HANDLER = r'''
def run_match_native110_current(stage):
    if (operation!='int-andromeda-match-native-current-20261001-v1'
            or payload.get('batch')!='native110-20260928'
            or payload.get('maximum_writes')!=0 or payload.get('provider_http_calls')!=0):
        fail('native110_fixed_scope')
    root=home/'.anytoour-match/operations'
    if not root.is_dir() or root.is_symlink() or root.resolve()!=root:
        fail('native110_private_root')
    child=root/operation
    if child.exists() or child.is_symlink(): fail('native110_exists_no_replay')
    child.mkdir(mode=0o700)
    reservation={'operation':operation,'source_sha':source,'batch':'native110-20260928',
                 'maximum_writes':0,'provider_http_calls':0,'state':'reserved_before_db_read',
                 'reserved_at':int(time.time())}
    fd=os.open(child/'reservation.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
    with os.fdopen(fd,'wb') as stream:
        stream.write(json.dumps(reservation,sort_keys=True,separators=(',',':')).encode()+b'\n')
        stream.flush();os.fsync(stream.fileno())
    runner=stage/'scripts/diagnostics/hotel_match_native110_current_v1.php'
    if not safe_file(runner,2*1024*1024): fail('native110_runner_missing_no_replay')
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(child),'MATCH_SOURCE_SHA':source})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',
                        '-d','disable_functions='+disabled,str(runner),'--current'],cwd=project,env=env,
                       capture_output=True,text=True,timeout=240)
    if run.returncode!=0 or run.stderr.strip() or len(run.stdout.encode())>262144:
        fail('native110_read_failed_no_replay')
    data=json.loads(run.stdout)
    fields={'state','operation','source_sha','batch','manifest_sha256','sources_requested','sources_examined',
            'protected_skipped','current_rows_returned','raw_verified_facts','raw_files_read','raw_bytes_read',
            'native_facts_examined','provider_http_calls','database_writes','mapping_writes','safe_to_write_now',
            'no_replay','acceptance_policy_changed','review_rows'}
    if (not isinstance(data,dict) or set(data)!=fields
            or data['state']!='completed_native110_current_review' or data['operation']!=operation
            or data['source_sha']!=source or data['batch']!='native110-20260928'
            or data['safe_to_write_now'] is not False or data['acceptance_policy_changed'] is not False
            or data['no_replay'] is not True): fail('native110_summary_contract')
    fixed={'sources_requested':110,'sources_examined':109,'protected_skipped':1,
           'current_rows_returned':110,'native_facts_examined':3262,
           'provider_http_calls':0,'database_writes':0,'mapping_writes':0}
    if any(type(data[k]) is not int or data[k]!=v for k,v in fixed.items()): fail('native110_summary_counts')
    for k,cap in (('raw_verified_facts',3262),('raw_files_read',1000),('raw_bytes_read',536870912)):
        if type(data[k]) is not int or not 0<=data[k]<=cap: fail('native110_summary_bounds')
    def shape(value,keys):
        if not isinstance(value,dict) or set(value)!=set(keys.split()): fail('native110_review_projection')
    def identity(value):
        if not isinstance(value,str) or not re.fullmatch(r'[1-9][0-9]{0,31}',value): fail('native110_review_identity')
    def count(value,cap=50000):
        if type(value) is not int or not 0<=value<=cap: fail('native110_review_count')
    def codes(value):
        if not isinstance(value,list) or len(value)>100 or any(not isinstance(v,str) or not re.fullmatch(r'[a-z_]{1,100}',v) for v in value): fail('native110_review_codes')
    def items(value,cap=128):
        if not isinstance(value,list) or len(value)>cap: fail('native110_review_items')
        return value
    namespaces={'operator_5','operator_115','operator_315','operator_342'}
    def native(value):
        identity(value.get('native_id'))
        if value.get('namespace') not in namespaces: fail('native110_review_namespace')
    reviews=data['review_rows'];items(reviews,110)
    if len(reviews)!=110: fail('native110_review_membership')
    seen=set()
    for row in reviews:
        shape(row,'catalog_id state safe_to_write_now holds catalog_digest_matches_saved evidence_digest_matches_saved source_history_id_matches native_checks operator_checks targets tv_checks')
        identity(row['catalog_id'])
        if row['catalog_id'] in seen or row['safe_to_write_now'] is not False: fail('native110_review_authority')
        seen.add(row['catalog_id']);codes(row['holds'])
        for key in ('catalog_digest_matches_saved','evidence_digest_matches_saved','source_history_id_matches'):
            if type(row[key]) is not bool: fail('native110_review_bool')
        if row['catalog_id']=='2000086118':
            if (row['state']!='protected_not_examined' or row['holds']
                    or any(row[k] for k in ('native_checks','operator_checks','targets','tv_checks',
                                           'catalog_digest_matches_saved','evidence_digest_matches_saved','source_history_id_matches'))): fail('native110_protected_readback')
            continue
        if row['state']!='current_review_observed': fail('native110_review_state')
        for f in items(row['native_checks']):
            shape(f,'namespace native_id global_saved_unique raw_verified failures');native(f);codes(f['failures'])
            if type(f['global_saved_unique']) is not bool or type(f['raw_verified']) is not bool: fail('native110_review_bool')
        for o in items(row['operator_checks']):
            shape(o,'namespace native_id current_identity_count current_local_hotel_ids');native(o);count(o['current_identity_count'])
            for id in items(o['current_local_hotel_ids'],50000):
                count(id,9223372036854775807)
                if id==0: fail('native110_review_identity')
        for t in items(row['targets'],200):
            shape(t,'kind id tv_live30_observed holds');count(t['id'],9223372036854775807);codes(t['holds'])
            if t['id']==0 or t['kind'] not in ('tv_candidate','independent_local_anchor') or type(t['tv_live30_observed']) is not bool: fail('native110_review_target')
        for c in items(row['tv_checks'],100):
            shape(c,'tv_hotel_id operator native_id tv_native_id state producers');count(c['tv_hotel_id'],9223372036854775807)
            identity(c['native_id']);identity(c['tv_native_id'])
            if c['tv_hotel_id']==0 or c['operator'] not in ('anex','bg','funsun','intourist') or c['state'] not in ('namespace_bridge_review_required','saved_producers_reviewed'): fail('native110_review_tv')
            for p in items(c['producers'],2):
                shape(p,'source_operation source_result_sha256 state failures');codes(p['failures'])
                if (not isinstance(p['source_operation'],str) or not re.fullmatch(r'hotel-match-[a-zA-Z0-9_-]{1,180}',p['source_operation'])
                        or not isinstance(p['source_result_sha256'],str) or not re.fullmatch(r'[a-f0-9]{64}',p['source_result_sha256'])
                        or p['state'] not in ('producer_unavailable','proof_hold','saved_tv_proof_verified')): fail('native110_review_producer')
    if '2000086118' not in seen: fail('native110_review_membership')
    manifest_path=child/'native110-current-manifest.json'
    summary_path=child/'native110-current-summary.json'
    if (not isinstance(data['manifest_sha256'],str) or not re.fullmatch(r'[a-f0-9]{64}',data['manifest_sha256'])
            or not safe_file(manifest_path,64*1024*1024) or not safe_file(summary_path,262144)
            or hashlib.sha256(manifest_path.read_bytes()).hexdigest()!=data['manifest_sha256']
            or safe_json(summary_path,262144)!=data): fail('native110_private_result_binding')
    return data

'''

REMOTE_NATIVE_DISPATCH = r'''    if mode=='match-native110-current':
        result['match_native110_current']=run_match_native110_current(stage)
        result['supplier_calls']=0
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''


REMOTE_GUARDED_HANDLER = r'''
def run_match_native110_write(stage):
    input_sha='59cfe4bf4001636f77a0e8ad440475c4d6b514599c9147fc984daad5f4f7849e'
    if (operation!='int-andromeda-match-native110-write-20261001-v1' or payload.get('batch')!='native110-20260928'
            or payload.get('maximum_writes')!=4 or payload.get('provider_http_calls')!=0
            or payload.get('input_sha256')!=input_sha): fail('guarded_fixed_scope')
    root=home/'.anytoour-match/operations'
    if not root.is_dir() or root.is_symlink() or root.resolve()!=root: fail('guarded_private_root')
    child=root/operation
    if child.exists() or child.is_symlink(): fail('guarded_exists_no_replay')
    reservation={'operation':operation,'source_sha':source,'batch':'native110-20260928','input_sha256':input_sha,
                 'maximum_writes':4,'provider_http_calls':0,'state':'reserved_before_db','reserved_at':int(time.time())}
    def exclusive(path,value):
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as stream:
            stream.write(json.dumps(value,sort_keys=True,separators=(',',':')).encode()+b'\n')
            stream.flush();os.fsync(stream.fileno())
    # A new name cannot consume the same checked raw/CURRENT intake twice.
    exclusive(root.parent/('native110-input-'+input_sha+'-consumed.json'),reservation)
    child.mkdir(mode=0o700);exclusive(child/'reservation.json',reservation)
    runner=stage/'scripts/diagnostics/hotel_match_native110_guarded_v1.php'
    if not safe_file(runner,2*1024*1024): fail('guarded_runner_missing_no_replay')
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(child),'MATCH_SOURCE_SHA':source})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',
                        '-d','disable_functions='+disabled,str(runner),'--execute'],cwd=project,env=env,
                       capture_output=True,text=True,timeout=240)
    result_path=child/'result.json';receipt_path=child/'receipt.json'
    if (not safe_file(result_path,262144) or not safe_file(receipt_path,65536) or run.stderr.strip()
            or len(run.stdout.encode())>262144): fail('guarded_terminal_missing_no_replay')
    data=safe_json(result_path,262144);receipt=safe_json(receipt_path,65536)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    if (json.loads(run.stdout)!=data or any(data.get(k)!=v or receipt.get(k)!=v for k,v in
            {'operation':operation,'source_sha':source,'batch':'native110-20260928','input_sha256':input_sha,
             'provider_http_calls':0,'no_replay':True}.items())
            or receipt.get('state')!=data.get('state') or receipt.get('result_sha256')!=digest): fail('guarded_terminal_binding')
    base={'state','current_candidates_evaluated','rows','held','database_writes','mapping_writes','readback_verified',
          'operation','source_sha','batch','input_sha256','provider_http_calls','no_replay'}
    optional={'reason','commit_attempted','commit_completed','effective_resolver_verified','prior_evidence_preserved',
              'unrelated_identities_unchanged','coverage_before','coverage_after','new_full_triples'}
    if not base.issubset(data) or set(data)-base-optional: fail('guarded_terminal_projection')
    if (any(type(v.get('provider_http_calls')) is not int or v['provider_http_calls']!=0 or v.get('no_replay') is not True for v in (data,receipt))
            or type(data['current_candidates_evaluated']) is not int or not 0<=data['current_candidates_evaluated']<=4): fail('guarded_terminal_counts')
    count=data['mapping_writes']
    if count is not None and (type(count) is not int or not 0<=count<=4): fail('guarded_write_count')
    if (data['database_writes']!=count or receipt.get('database_writes')!=count or receipt.get('mapping_writes')!=count
            or type(data['readback_verified']) is not bool or receipt.get('readback_verified')!=data['readback_verified']): fail('guarded_terminal_count_binding')
    expected={'3126':42903,'9501':420,'475947':28529,'2000034238':16944};seen=set()
    for key in ('rows','held'):
        rows=data[key]
        if not isinstance(rows,list) or len(rows)>4: fail('guarded_row_shape')
        for row in rows:
            fields={'catalog_id','local_hotel_id','status','reasons'} if key=='held' else {'catalog_id','local_hotel_id','name','catalog_sha256','evidence_sha256','prior_evidence_sha256','proof_operator_count'}
            if not isinstance(row,dict) or set(row)!=fields: fail('guarded_row_shape')
            cat=row['catalog_id']
            if cat not in expected or cat in seen or type(row['local_hotel_id']) is not int or row['local_hotel_id']!=expected[cat]: fail('guarded_row_scope')
            seen.add(cat)
            if key=='held':
                if row['status']!='hold' or not isinstance(row['reasons'],list) or len(row['reasons'])>100 or any(not isinstance(r,str) or not re.fullmatch(r'[a-z_][a-z0-9_]{0,99}',r) for r in row['reasons']): fail('guarded_hold_projection')
            else:
                if (not isinstance(row['name'],str) or len(row['name'])>1000 or type(row['proof_operator_count']) is not int
                        or not 1<=row['proof_operator_count']<=4 or any(not isinstance(row[k],str) or not re.fullmatch(r'[a-f0-9]{64}',row[k]) for k in ('catalog_sha256','evidence_sha256','prior_evidence_sha256'))): fail('guarded_written_projection')
    for key in ('commit_attempted','commit_completed','effective_resolver_verified','prior_evidence_preserved','unrelated_identities_unchanged'):
        if key in data and type(data[key]) is not bool: fail('guarded_terminal_bool')
    if 'reason' in data and (not isinstance(data['reason'],str) or not re.fullmatch(r'[a-z_]{1,100}',data['reason'])): fail('guarded_reason')
    for key in ('coverage_before','coverage_after'):
        if key in data:
            c=data[key]
            if not isinstance(c,dict) or set(c)!={'tv_total','full_triple','samo_only','anex_only','neither'} or any(type(v) is not int or not 0<=v<=50000 for v in c.values()): fail('guarded_coverage_projection')
    if 'new_full_triples' in data and (type(data['new_full_triples']) is not int or not -4<=data['new_full_triples']<=4): fail('guarded_coverage_projection')
    successful=data['state'] in ('committed_readback_verified','completed_no_new_writes')
    if successful:
        if (run.returncode!=0 or type(count) is not int or data['readback_verified'] is not True
                or type(data['current_candidates_evaluated']) is not int or data['current_candidates_evaluated']!=4
                or set(expected)!=seen or len(data['rows'])!=count): fail('guarded_success_contract')
        if data['state']=='committed_readback_verified' and (count<1 or any(data.get(k) is not True for k in
                ('commit_completed','commit_attempted','effective_resolver_verified','prior_evidence_preserved','unrelated_identities_unchanged'))): fail('guarded_commit_contract')
        if data['state']=='completed_no_new_writes' and count!=0: fail('guarded_zero_contract')
    elif data['state'] not in ('failed_before_writer','rolled_back_no_writes','commit_outcome_unknown_no_replay',
                             'committed_readback_unconfirmed','write_outcome_unknown_no_replay') or data['readback_verified']:
        fail('guarded_false_verification')
    return {'successful':successful,'exit_code':run.returncode,'result_sha256':digest,'summary':data}

'''

REMOTE_GUARDED_DISPATCH = r'''    if mode=='match-native110-write':
        guarded=run_match_native110_write(stage)
        result['match_native110_write']=guarded
        result['supplier_calls']=0
        result['database_writes']=guarded['summary']['database_writes']
        result['mapping_writes']=guarded['summary']['mapping_writes']
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete' if guarded['successful'] else 'terminal_nonzero_no_replay'
'''


REMOTE_BG_HANDLER = r'''
def run_match_native110_bg_evidence(stage):
    input_sha='59cfe4bf4001636f77a0e8ad440475c4d6b514599c9147fc984daad5f4f7849e'
    if (operation!='int-andromeda-match-native110-bg-evidence-20261001-v1' or payload.get('batch')!='native110-20260928'
            or payload.get('maximum_writes')!=0 or payload.get('provider_http_calls')!=0
            or payload.get('input_sha256')!=input_sha): fail('bg_fixed_scope')
    root=home/'.anytoour-match/operations'
    if not root.is_dir() or root.is_symlink() or root.resolve()!=root: fail('bg_private_root')
    child=root/operation
    if child.exists() or child.is_symlink(): fail('bg_exists_no_replay')
    child.mkdir(mode=0o700)
    reservation={'operation':operation,'source_sha':source,'batch':'native110-20260928','input_sha256':input_sha,
                 'maximum_writes':0,'provider_http_calls':0,'state':'reserved_before_saved_read','reserved_at':int(time.time())}
    fd=os.open(child/'reservation.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
    with os.fdopen(fd,'wb') as stream:
        stream.write(json.dumps(reservation,sort_keys=True,separators=(',',':')).encode()+b'\n');stream.flush();os.fsync(stream.fileno())
    runner=stage/'scripts/diagnostics/hotel_match_native110_bg_evidence_v1.php'
    if not safe_file(runner,2*1024*1024): fail('bg_runner_missing_no_replay')
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'MATCH_OPERATION_DIR':str(child),'MATCH_SOURCE_SHA':source})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0','-d','disable_functions='+disabled,
                        str(runner),'--read-saved'],cwd=project,env=env,capture_output=True,text=True,timeout=240)
    path=child/'result.json'
    if run.returncode!=0 or run.stderr.strip() or len(run.stdout.encode())>262144 or not safe_file(path,262144): fail('bg_read_failed_no_replay')
    data=json.loads(run.stdout)
    fields={'state','operation','source_sha','batch','input_sha256','rows','raw_files_read','raw_bytes_read','provider_http_calls',
            'database_reads','database_writes','mapping_writes','safe_to_write_now','no_replay'}
    if (not isinstance(data,dict) or set(data)!=fields or safe_json(path,262144)!=data
            or data['state']!='completed_bg_original_fields_review' or data['operation']!=operation or data['source_sha']!=source
            or data['batch']!='native110-20260928' or data['input_sha256']!=input_sha
            or data['safe_to_write_now'] is not False or data['no_replay'] is not True): fail('bg_output_binding')
    for key,cap in [('provider_http_calls',0),('database_reads',0),('database_writes',0),('mapping_writes',0),('raw_files_read',1000),('raw_bytes_read',536870912)]:
        if type(data[key]) is not int or not 0<=data[key]<=cap: fail('bg_output_counts')
    expected=__BG_EXPECTED__
    rows=data['rows']
    if not isinstance(rows,list) or len(rows)!=18: fail('bg_output_rows')
    seen=set()
    def shape(value,fields):
        if not isinstance(value,dict) or set(value)!=set(fields.split()): fail('bg_projection')
    def items(value,cap):
        if not isinstance(value,list) or len(value)>cap: fail('bg_projection')
        return value
    def source_field(value):
        if not isinstance(value,str) or not re.fullmatch(r'(?:row|original)\.[a-zA-Z_][a-zA-Z0-9_.-]{0,79}',value): fail('bg_field')
    def sha(value):
        if not isinstance(value,str) or not re.fullmatch(r'[a-f0-9]{64}',value): fail('bg_digest')
    for row in rows:
        shape(row,'catalog_id tv_hotel_id samo_native_id tv_native_id raw_references_examined top_fields original_fields location_fields bg_links failures safe_to_write_now')
        cat=row['catalog_id']
        if (cat not in expected or cat in seen or type(row['tv_hotel_id']) is not int
                or tuple(row[k] for k in ('tv_hotel_id','samo_native_id','tv_native_id'))!=expected[cat]
                or row['safe_to_write_now'] is not False or type(row['raw_references_examined']) is not int
                or not 0<=row['raw_references_examined']<=1000): fail('bg_row_scope')
        seen.add(cat)
        for key in ('top_fields','original_fields'):
            for value in items(row[key],256):
                if not isinstance(value,str) or not re.fullmatch(r'[a-zA-Z_][a-zA-Z0-9_.-]{0,79}',value): fail('bg_field_inventory')
        for failure in items(row['failures'],10):
            if failure not in ('raw_file_unavailable','raw_reference_changed','raw_evidence_reference_missing'): fail('bg_failure')
        for geo in items(row['location_fields'],128):
            shape(geo,'source_field value');source_field(geo['source_field']);value=geo['value']
            if not re.fullmatch(r'(?:town|city|state|country|latitude|longitude|lat|lng|lon|townkey|townname|hotelLat|hotelLng|hotelLatitude|hotelLongitude|hotelTown|hotelCountry)',geo['source_field'].split('.',1)[1],re.I): fail('bg_location_field')
            if value is not None and (type(value) not in (str,int,float) or (type(value) is str and not re.fullmatch(r"[\w\s.,+'’()/_-]{1,180}",value)) or (type(value) in (int,float) and not (value==value and abs(value)<=10**15))): fail('bg_location_projection')
        for link in items(row['bg_links'],128):
            shape(link,'source_field host url_sha256 signed_parameters_present hotel_selectors');source_field(link['source_field']);sha(link['url_sha256'])
            if not re.fullmatch(r'(?:(?:hotel|object).*(?:url|link)|url|link)',link['source_field'].split('.',1)[1],re.I): fail('bg_link_field')
            if (not isinstance(link['host'],str) or not re.fullmatch(r'(?:[a-z0-9-]+\.)*bgoperator\.ru',link['host'])
                    or type(link['signed_parameters_present']) is not bool): fail('bg_link_projection')
            for selector in items(link['hotel_selectors'],32):
                shape(selector,'parameter positive_tokens opaque_tokens value_sha256');sha(selector['value_sha256'])
                if (not isinstance(selector['parameter'],str) or not re.fullmatch(r'(?:id|tid|hotel|hotelid|hotel_id|hotels|hotels\[\]|hotellist|i1hotelinc)',selector['parameter'],re.I)
                        or type(selector['opaque_tokens']) is not int or not 0<=selector['opaque_tokens']<=128): fail('bg_selector_projection')
                for token in items(selector['positive_tokens'],128):
                    if not isinstance(token,str) or not re.fullmatch(r'[1-9][0-9]{0,31}',token): fail('bg_selector_projection')
    return data

'''

REMOTE_BG_DISPATCH = r'''    if mode=='match-native110-bg-evidence':
        result['match_native110_bg_evidence']=run_match_native110_bg_evidence(stage)
        result['supplier_calls']=0
        result['database_reads']=0
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''
REMOTE_BG_HANDLER = REMOTE_BG_HANDLER.replace('__BG_EXPECTED__',repr(BG_EXPECTED))


REMOTE_SHAMS_GEO_HANDLER = r'''
def validate_match_shams_geo(data,path,expected_source):
    input_sha='59cfe4bf4001636f77a0e8ad440475c4d6b514599c9147fc984daad5f4f7849e'
    fields={'schema','state','operation','batch','source_sha','input_sha256','no_replay','catalog_id','tv_hotel_id',
            'snapshot_captured_at_utc','saved_target_geography','source_history_geography_exported','raw_files_read','raw_bytes_read',
            'references_examined','references','database_reads','provider_http_calls','database_writes','mapping_writes','safe_to_write_now'}
    if (not isinstance(data,dict) or set(data)!=fields or safe_json(path,524288)!=data
            or data['schema']!='match-shams-saved-geography/1' or data['state']!='completed_saved_geography_evidence'
            or data['operation']!='int-andromeda-match-shams-geo-evidence-20261001-v1' or data['source_sha']!=expected_source
            or data['batch']!='native110-20260928' or data['input_sha256']!=input_sha
            or data['catalog_id']!='9501' or data['tv_hotel_id']!=420
            or data['source_history_geography_exported'] is not False or data['safe_to_write_now'] is not False
            or data['no_replay'] is not True): fail('shams_geo_output_binding')
    for key,cap in [('provider_http_calls',0),('database_reads',0),('database_writes',0),('mapping_writes',0),
                    ('raw_files_read',8),('raw_bytes_read',67108864),('references_examined',128)]:
        if type(data[key]) is not int or not 0<=data[key]<=cap: fail('shams_geo_output_counts')
    if (not isinstance(data['snapshot_captured_at_utc'],str)
            or not re.fullmatch(r'20[0-9]{2}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:\.[0-9]{1,6})?(?:Z|\+00:00)',data['snapshot_captured_at_utc'])): fail('shams_geo_snapshot')
    def shape(value,fields):
        if not isinstance(value,dict) or set(value)!=set(fields.split()): fail('shams_geo_projection')
    def items(value,cap):
        if not isinstance(value,list) or len(value)>cap: fail('shams_geo_projection')
        return value
    def field_name(value):
        if not isinstance(value,str) or not re.fullmatch(r'[a-zA-Z_][a-zA-Z0-9_.-]{0,79}',value): fail('shams_geo_field')
    def geo_value(value):
        if value is not None and (type(value) not in (str,int,float)
                or (type(value) is str and not re.fullmatch(r"[\w\s.,+'’()/_-]{1,180}",value))
                or (type(value) in (int,float) and not (value==value and abs(value)<=10**15))): fail('shams_geo_value')
    target_fields={'country_name','region_name','subregion_name','latitude','longitude'}
    for geo in items(data['saved_target_geography'],64):
        shape(geo,'source_field value')
        if not isinstance(geo['source_field'],str) or not geo['source_field'].startswith('saved_target.') or geo['source_field'].split('.',1)[1] not in target_fields: fail('shams_target_geo_field')
        geo_value(geo['value'])
    refs=items(data['references'],128)
    if data['references_examined']!=len(refs) or not refs: fail('shams_geo_reference_count')
    seen=set()
    allowed={('operator_5','835'),('operator_342','24402')}
    geo_fields={'town','city','state','country','country_name','region_name','subregion_name','latitude','longitude','lat','lng','lon',
                'townKey','townName','stateName','countryName','cityName','hotelLat','hotelLng','hotelLatitude','hotelLongitude','hotelTown','hotelCountry'}
    for ref in refs:
        shape(ref,'namespace native_id page_sha256 json_pointer field_names original_field_names location_fields raw_verified failures')
        pair=(ref['namespace'],ref['native_id'])
        if (pair not in allowed or not isinstance(ref['page_sha256'],str) or not re.fullmatch(r'[a-f0-9]{64}',ref['page_sha256'])
                or not isinstance(ref['json_pointer'],str) or not re.fullmatch(r'/(?:PRICES|prices)/[0-9]{1,8}',ref['json_pointer'])
                or type(ref['raw_verified']) is not bool): fail('shams_geo_reference_scope')
        seen.add(pair)
        for key in ('field_names','original_field_names'):
            for value in items(ref[key],256): field_name(value)
        for failure in items(ref['failures'],1):
            if failure!='saved_geo_reference_unverified': fail('shams_geo_failure')
        if ref['raw_verified']!=(not ref['failures']): fail('shams_geo_verification_state')
        for geo in items(ref['location_fields'],64):
            shape(geo,'source_field value')
            if (not isinstance(geo['source_field'],str) or not re.fullmatch(r'(?:row|original)\.[a-zA-Z_][a-zA-Z0-9_.-]{0,79}',geo['source_field'])
                    or geo['source_field'].split('.',1)[1] not in geo_fields): fail('shams_source_geo_field')
            geo_value(geo['value'])
    if seen!=allowed: fail('shams_geo_native_coverage')
    return data

def run_match_shams_geo_evidence(stage):
    input_sha='59cfe4bf4001636f77a0e8ad440475c4d6b514599c9147fc984daad5f4f7849e'
    if (operation!='int-andromeda-match-shams-geo-evidence-20261001-v1' or payload.get('batch')!='native110-20260928'
            or payload.get('maximum_writes')!=0 or payload.get('provider_http_calls')!=0
            or payload.get('input_sha256')!=input_sha): fail('shams_geo_fixed_scope')
    root=home/'.anytoour-match/operations'
    if not root.is_dir() or root.is_symlink() or root.resolve()!=root: fail('shams_geo_private_root')
    child=root/operation
    if child.exists() or child.is_symlink(): fail('shams_geo_exists_no_replay')
    child.mkdir(mode=0o700)
    reservation={'operation':operation,'source_sha':source,'batch':'native110-20260928','input_sha256':input_sha,
                 'maximum_writes':0,'provider_http_calls':0,'state':'reserved_before_saved_read','reserved_at':int(time.time())}
    fd=os.open(child/'reservation.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
    with os.fdopen(fd,'wb') as stream:
        stream.write(json.dumps(reservation,sort_keys=True,separators=(',',':')).encode()+b'\n');stream.flush();os.fsync(stream.fileno())
    runner=stage/'scripts/diagnostics/hotel_match_shams_geography_saved_v1.php'
    if not safe_file(runner,2*1024*1024): fail('shams_geo_runner_missing_no_replay')
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'MATCH_OPERATION_DIR':str(child),'MATCH_SOURCE_SHA':source})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0','-d','disable_functions='+disabled,
                        str(runner),'--read-saved'],cwd=project,env=env,capture_output=True,text=True,timeout=240)
    path=child/'result.json'
    if run.returncode!=0 or run.stderr.strip() or len(run.stdout.encode())>524288 or not safe_file(path,524288): fail('shams_geo_read_failed_no_replay')
    data=json.loads(run.stdout)
    return validate_match_shams_geo(data,path,source)

'''

REMOTE_SHAMS_GEO_DISPATCH = r'''    if mode=='match-shams-geo-evidence':
        result['match_shams_geo_evidence']=run_match_shams_geo_evidence(stage)
        result['supplier_calls']=0
        result['database_reads']=0
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''

REMOTE_SHAMS_GEO_READBACK_HANDLER = REMOTE_SHAMS_GEO_HANDLER + r'''
def run_match_shams_geo_readback(stage):
    input_sha='59cfe4bf4001636f77a0e8ad440475c4d6b514599c9147fc984daad5f4f7849e'
    evidence_operation='int-andromeda-match-shams-geo-evidence-20261001-v1'
    evidence_source='12dc06dbdfd047c05caa346092cb9bd1c1dd0323'
    if (operation!='int-andromeda-match-shams-geo-readback-20261001-v1' or payload.get('batch')!='native110-20260928'
            or payload.get('maximum_writes')!=0 or payload.get('provider_http_calls')!=0
            or payload.get('input_sha256')!=input_sha): fail('shams_geo_readback_fixed_scope')
    root=home/'.anytoour-match/operations'
    if not root.is_dir() or root.is_symlink() or root.resolve()!=root: fail('shams_geo_readback_private_root')
    child=root/operation
    if child.exists() or child.is_symlink(): fail('shams_geo_readback_exists_no_replay')
    evidence=root/evidence_operation
    evidence_path=evidence/'result.json'
    if (not evidence.is_dir() or evidence.is_symlink() or evidence.resolve()!=evidence
            or not safe_file(evidence/'reservation.json',1048576) or not safe_file(evidence_path,524288)): fail('shams_geo_terminal_missing')
    evidence_reservation=safe_json(evidence/'reservation.json',1048576)
    if (evidence_reservation.get('operation')!=evidence_operation or evidence_reservation.get('source_sha')!=evidence_source
            or evidence_reservation.get('batch')!='native110-20260928' or evidence_reservation.get('input_sha256')!=input_sha
            or evidence_reservation.get('maximum_writes')!=0 or evidence_reservation.get('provider_http_calls')!=0): fail('shams_geo_terminal_binding')
    child.mkdir(mode=0o700)
    reservation={'operation':operation,'source_sha':source,'batch':'native110-20260928','input_sha256':input_sha,
                 'evidence_operation':evidence_operation,'evidence_source_sha':evidence_source,
                 'maximum_writes':0,'provider_http_calls':0,'state':'reserved_before_terminal_readback','reserved_at':int(time.time())}
    fd=os.open(child/'reservation.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
    with os.fdopen(fd,'wb') as stream:
        stream.write(json.dumps(reservation,sort_keys=True,separators=(',',':')).encode()+b'\n');stream.flush();os.fsync(stream.fileno())
    data=validate_match_shams_geo(safe_json(evidence_path,524288),evidence_path,evidence_source)
    output={'state':'completed_saved_geography_readback','operation':operation,'source_sha':source,'batch':'native110-20260928',
            'input_sha256':input_sha,'evidence_operation':evidence_operation,'evidence_source_sha':evidence_source,
            'provider_http_calls':0,'database_reads':0,'database_writes':0,'mapping_writes':0,'safe_to_write_now':False,
            'no_replay':True,'evidence':data}
    fd=os.open(child/'result.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
    with os.fdopen(fd,'wb') as stream:
        stream.write(json.dumps(output,sort_keys=True,separators=(',',':'),ensure_ascii=False).encode()+b'\n');stream.flush();os.fsync(stream.fileno())
    return output

'''

REMOTE_SHAMS_GEO_READBACK_DISPATCH = r'''    if mode=='match-shams-geo-readback':
        result['match_shams_geo_readback']=run_match_shams_geo_readback(stage)
        result['supplier_calls']=0
        result['database_reads']=0
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''

REMOTE_SHAMS_WRITE_HANDLER = r'''
def run_match_shams_write(stage):
    input_sha='59cfe4bf4001636f77a0e8ad440475c4d6b514599c9147fc984daad5f4f7849e'
    geo_operation='int-andromeda-match-shams-geo-readback-20261001-v1'
    if (operation!='int-andromeda-match-shams-current-write-20261001-v1' or payload.get('batch')!='shams9501-geo-20261001'
            or payload.get('maximum_writes')!=1 or payload.get('provider_http_calls')!=0
            or payload.get('input_sha256')!=input_sha or payload.get('geography_operation')!=geo_operation): fail('shams_write_fixed_scope')
    root=home/'.anytoour-match/operations'
    if not root.is_dir() or root.is_symlink() or root.resolve()!=root: fail('shams_write_private_root')
    geo=root/geo_operation
    if (not geo.is_dir() or geo.is_symlink() or geo.resolve()!=geo or not safe_file(geo/'result.json',1048576)): fail('shams_write_geo_receipt_missing')
    geo_data=safe_json(geo/'result.json',1048576)
    if (geo_data.get('state')!='completed_saved_geography_readback' or geo_data.get('operation')!=geo_operation
            or geo_data.get('source_sha')!='12dc06dbdfd047c05caa346092cb9bd1c1dd0323' or geo_data.get('input_sha256')!=input_sha
            or geo_data.get('no_replay') is not True or geo_data.get('mapping_writes')!=0 or geo_data.get('provider_http_calls')!=0): fail('shams_write_geo_receipt_binding')
    child=root/operation
    if child.exists() or child.is_symlink(): fail('shams_write_exists_no_replay')
    reservation={'operation':operation,'source_sha':source,'batch':'shams9501-geo-20261001','input_sha256':input_sha,
                 'geography_operation':geo_operation,'maximum_writes':1,'provider_http_calls':0,'state':'reserved_before_db','reserved_at':int(time.time())}
    def exclusive(path,value):
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as stream:
            stream.write(json.dumps(value,sort_keys=True,separators=(',',':')).encode()+b'\n');stream.flush();os.fsync(stream.fileno())
    exclusive(root.parent/'shams9501-geo-20261001-consumed.json',reservation)
    child.mkdir(mode=0o700);exclusive(child/'reservation.json',reservation)
    runner=stage/'scripts/diagnostics/hotel_match_shams_guarded_v1.php'
    if not safe_file(runner,2*1024*1024): fail('shams_write_runner_missing_no_replay')
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(child),'MATCH_SOURCE_SHA':source})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0','-d','disable_functions='+disabled,
                        str(runner),'--execute'],cwd=project,env=env,capture_output=True,text=True,timeout=240)
    result_path=child/'result.json';receipt_path=child/'receipt.json'
    if not safe_file(result_path,262144) or not safe_file(receipt_path,65536) or run.stderr.strip() or len(run.stdout.encode())>262144: fail('shams_write_terminal_missing_no_replay')
    data=safe_json(result_path,262144);receipt=safe_json(receipt_path,65536);digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    fixed={'operation':operation,'source_sha':source,'batch':'shams9501-geo-20261001','input_sha256':input_sha,
           'geography_operation':geo_operation,'provider_http_calls':0,'no_replay':True}
    if (json.loads(run.stdout)!=data or any(data.get(k)!=v or receipt.get(k)!=v for k,v in fixed.items())
            or receipt.get('state')!=data.get('state') or receipt.get('result_sha256')!=digest): fail('shams_write_terminal_binding')
    if (data.get('no_replay') is not True or receipt.get('no_replay') is not True
            or type(data.get('provider_http_calls')) is not int or data['provider_http_calls']!=0
            or type(receipt.get('provider_http_calls')) is not int or receipt['provider_http_calls']!=0): fail('shams_write_terminal_binding')
    base={'state','current_candidates_evaluated','rows','held','database_writes','mapping_writes','readback_verified'}|set(fixed)
    optional={'reason','commit_attempted','commit_completed','effective_resolver_verified','prior_evidence_preserved','unrelated_identities_unchanged','coverage_before','coverage_after','new_full_triples'}
    if not base.issubset(data) or set(data)-base-optional: fail('shams_write_terminal_projection')
    if type(data['current_candidates_evaluated']) is not int or not 0<=data['current_candidates_evaluated']<=1: fail('shams_write_count')
    count=data['mapping_writes']
    if count is not None and (type(count) is not int or not 0<=count<=1): fail('shams_write_count')
    if data['database_writes']!=count or receipt.get('database_writes')!=count or receipt.get('mapping_writes')!=count or type(data['readback_verified']) is not bool or receipt.get('readback_verified')!=data['readback_verified']: fail('shams_write_count_binding')
    seen=set()
    for key in ('rows','held'):
        rows=data[key]
        if not isinstance(rows,list) or len(rows)>1: fail('shams_write_row_shape')
        for row in rows:
            fields={'catalog_id','local_hotel_id','status','reasons'} if key=='held' else {'catalog_id','local_hotel_id','name','catalog_sha256','evidence_sha256','prior_evidence_sha256','proof_operator_count'}
            if not isinstance(row,dict) or set(row)!=fields or row.get('catalog_id')!='9501' or row.get('local_hotel_id')!=420 or '9501' in seen: fail('shams_write_row_scope')
            seen.add('9501')
            if key=='held' and (row['status']!='hold' or not isinstance(row['reasons'],list) or not row['reasons'] or any(not isinstance(r,str) or not re.fullmatch(r'[a-z_][a-z0-9_]{0,99}',r) for r in row['reasons'])): fail('shams_write_hold_projection')
            if key=='rows' and (not isinstance(row['name'],str) or type(row['proof_operator_count']) is not int or not 1<=row['proof_operator_count']<=2 or any(not isinstance(row[k],str) or not re.fullmatch(r'[a-f0-9]{64}',row[k]) for k in ('catalog_sha256','evidence_sha256','prior_evidence_sha256'))): fail('shams_write_row_projection')
    successful=data['state'] in ('committed_readback_verified','completed_no_new_writes')
    if successful:
        if run.returncode!=0 or data['current_candidates_evaluated']!=1 or seen!={'9501'} or type(count) is not int or data['readback_verified'] is not True or len(data['rows'])!=count: fail('shams_write_success_contract')
        if data['state']=='committed_readback_verified' and (count!=1 or any(data.get(k) is not True for k in ('commit_attempted','commit_completed','effective_resolver_verified','prior_evidence_preserved','unrelated_identities_unchanged'))): fail('shams_write_commit_contract')
        if data['state']=='completed_no_new_writes' and count!=0: fail('shams_write_zero_contract')
    elif data['state'] not in ('failed_before_writer','rolled_back_no_writes','commit_outcome_unknown_no_replay','committed_readback_unconfirmed','write_outcome_unknown_no_replay') or data['readback_verified']:
        fail('shams_write_false_verification')
    return {'successful':successful,'exit_code':run.returncode,'result_sha256':digest,'summary':data}
'''

REMOTE_SHAMS_WRITE_DISPATCH = r'''    if mode=='match-shams-current-write':
        shams_write=run_match_shams_write(stage)
        result['match_shams_write']=shams_write
        result['supplier_calls']=0
        result['database_writes']=shams_write['summary']['database_writes']
        result['mapping_writes']=shams_write['summary']['mapping_writes']
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete' if shams_write['successful'] else 'terminal_nonzero_no_replay'
'''


REMOTE_TARGET_HANDLER = r'''
def run_match_tv_live30_target_catalog(stage):
    if (operation!='int-andromeda-match-live30-target-catalog-20261001-v1'
            or payload.get('batch')!='tv-live30-targets-20261001'
            or payload.get('maximum_writes')!=0 or payload.get('provider_http_calls')!=0): fail('target_catalog_fixed_scope')
    root=home/'.anytoour-match/operations'
    if not root.is_dir() or root.is_symlink() or root.resolve()!=root: fail('target_catalog_private_root')
    child=root/operation
    if child.exists() or child.is_symlink(): fail('target_catalog_exists_no_replay')
    child.mkdir(mode=0o700)
    reservation={'operation':operation,'source_sha':source,'batch':'tv-live30-targets-20261001',
                 'maximum_writes':0,'provider_http_calls':0,'state':'reserved_before_db_read','reserved_at':int(time.time())}
    fd=os.open(child/'reservation.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
    with os.fdopen(fd,'wb') as stream:
        stream.write(json.dumps(reservation,sort_keys=True,separators=(',',':')).encode()+b'\n');stream.flush();os.fsync(stream.fileno())
    runner=stage/'scripts/diagnostics/hotel_match_tv_live30_target_catalog_v1.php'
    if not safe_file(runner,2*1024*1024): fail('target_catalog_runner_missing_no_replay')
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(child),'MATCH_SOURCE_SHA':source})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0','-d','disable_functions='+disabled,
                        str(runner),'--current-targets'],cwd=project,env=env,capture_output=True,text=True,timeout=240)
    if run.returncode!=0 or run.stderr.strip() or len(run.stdout.encode())>8388608: fail('target_catalog_read_failed_no_replay')
    result_path=child/'result.json';receipt_path=child/'receipt.json'
    if not safe_file(result_path,8388608) or not safe_file(receipt_path,65536): fail('target_catalog_terminal_missing_no_replay')
    data=safe_json(result_path,8388608);receipt=safe_json(receipt_path,65536)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    if json.loads(run.stdout)!=data: fail('target_catalog_stdout_binding')
    validate_match_tv_live30_target_catalog(data,receipt,digest,operation,source)
    return {'result_sha256':digest,'summary':data}

def validate_match_tv_live30_target_catalog(data,receipt,digest,expected_operation,expected_source):
    fixed={'state':'completed_tv_live30_target_catalog','operation':expected_operation,'source_sha':expected_source,'batch':'tv-live30-targets-20261001',
           'provider_http_calls':0,'database_writes':0,'mapping_writes':0,'safe_to_write_now':False,'no_replay':True}
    fields=set(fixed)|{'captured_at_utc','row_count','rows'}
    if (set(data)!=fields or set(receipt)!=(fields-{'rows'})|{'result_sha256'}
            or any(data.get(k)!=v or receipt.get(k)!=v for k,v in fixed.items())
            or receipt.get('result_sha256')!=digest): fail('target_catalog_terminal_binding')
    for value in (data,receipt):
        if (value.get('safe_to_write_now') is not False or value.get('no_replay') is not True
                or any(type(value[k]) is not int or value[k]!=0 for k in ('provider_http_calls','database_writes','mapping_writes'))): fail('target_catalog_zero_authority')
    if (not isinstance(data['captured_at_utc'],str) or not re.fullmatch(r'[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z',data['captured_at_utc'])
            or receipt['captured_at_utc']!=data['captured_at_utc'] or type(data['row_count']) is not int
            or not 0<=data['row_count']<=20000 or type(receipt['row_count']) is not int or receipt['row_count']!=data['row_count']
            or not isinstance(data['rows'],list) or len(data['rows'])!=data['row_count']): fail('target_catalog_count_binding')
    seen=set();row_fields={'id','name','country_id','country_name','region_name','subregion_name','category','is_active',
                          'latitude','longitude','accepted_samo_ids','manual_hold','exclusion_hold'}
    for row in data['rows']:
        if (not isinstance(row,dict) or set(row)!=row_fields or type(row['id']) is not int or row['id']<=0
                or row['id'] in seen or row['is_active'] is not True): fail('target_catalog_row_identity')
        seen.add(row['id'])
        for key in ('name','country_id','country_name','region_name','subregion_name','category'):
            v=row[key]
            if v is not None and (not isinstance(v,str) or len(v.encode())>512 or re.search(r'[\x00-\x1f\x7f]|https?://',v,re.I)): fail('target_catalog_row_text')
        if not row['name']: fail('target_catalog_row_name')
        for key,bound in (('latitude',90),('longitude',180)):
            v=row[key]
            if v is not None and (type(v) not in (int,float) or not -bound<=v<=bound): fail('target_catalog_row_coordinates')
        for key in ('manual_hold','exclusion_hold'):
            if type(row[key]) is not bool: fail('target_catalog_row_bool')
        occupants=row['accepted_samo_ids']
        if (not isinstance(occupants,list) or len(occupants)>32
                or any(not isinstance(v,str) or not re.fullmatch(r'[1-9][0-9]{0,31}',v) for v in occupants)
                or occupants!=sorted(set(occupants))): fail('target_catalog_row_occupants')

'''

REMOTE_TARGET_DISPATCH = r'''    if mode=='match-tv-live30-target-catalog':
        result['match_tv_live30_target_catalog']=run_match_tv_live30_target_catalog(stage)
        result['supplier_calls']=0
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''


REMOTE_TARGET_PREFLIGHT_HANDLER = r'''
def run_match_tv_live30_target_preflight(stage):
    if (operation!='int-andromeda-match-live30-target-preflight-20261001-v1'
            or payload.get('batch')!='tv-live30-target-preflight-20261001'
            or payload.get('maximum_writes')!=0 or payload.get('provider_http_calls')!=0): fail('target_preflight_fixed_scope')
    root=home/'.anytoour-match/operations'
    if not root.is_dir() or root.is_symlink() or root.resolve()!=root: fail('target_preflight_private_root')
    child=root/operation
    if child.exists() or child.is_symlink(): fail('target_preflight_exists_no_replay')
    child.mkdir(mode=0o700)
    reservation={'operation':operation,'source_sha':source,'batch':'tv-live30-target-preflight-20261001',
                 'maximum_writes':0,'provider_http_calls':0,'state':'reserved_before_db_read','reserved_at':int(time.time())}
    fd=os.open(child/'reservation.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
    with os.fdopen(fd,'wb') as stream:
        stream.write(json.dumps(reservation,sort_keys=True,separators=(',',':')).encode()+b'\n');stream.flush();os.fsync(stream.fileno())
    runner=stage/'scripts/diagnostics/hotel_match_tv_live30_target_preflight_v1.php'
    if not safe_file(runner,2*1024*1024): fail('target_preflight_runner_missing_no_replay')
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(child),'MATCH_SOURCE_SHA':source})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0','-d','disable_functions='+disabled,
                        str(runner),'--current-target-preflight'],cwd=project,env=env,capture_output=True,text=True,timeout=240)
    if run.returncode!=0 or run.stderr.strip() or len(run.stdout.encode())>262144: fail('target_preflight_read_failed_no_replay')
    result_path=child/'result.json';receipt_path=child/'receipt.json'
    if not safe_file(result_path,262144) or not safe_file(receipt_path,65536): fail('target_preflight_terminal_missing_no_replay')
    data=safe_json(result_path,262144);receipt=safe_json(receipt_path,65536)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    if json.loads(run.stdout)!=data: fail('target_preflight_stdout_binding')
    validate_match_tv_live30_target_preflight(data,receipt,digest,operation,source)
    return {'result_sha256':digest,'summary':data}

def validate_match_tv_live30_target_preflight(data,receipt,digest,expected_operation,expected_source):
    fixed={'state':'completed_tv_live30_target_preflight','operation':expected_operation,'source_sha':expected_source,
           'batch':'tv-live30-target-preflight-20261001','provider_http_calls':0,'database_reads':1,
           'database_writes':0,'mapping_writes':0,'safe_to_write_now':False,'no_replay':True}
    details={'missing_columns','query_status','metrics'}
    fields=set(fixed)|{'captured_at_utc','schema_complete'}|details
    if (not isinstance(data,dict) or not isinstance(receipt,dict) or set(data)!=fields
            or set(receipt)!=(fields-details)|{'result_sha256'}
            or any(data.get(k)!=v or receipt.get(k)!=v for k,v in fixed.items())
            or receipt.get('result_sha256')!=digest): fail('target_preflight_terminal_binding')
    if (data.get('safe_to_write_now') is not False or data.get('no_replay') is not True
            or type(data.get('database_reads')) is not int or data['database_reads']!=1
            or any(type(data[k]) is not int or data[k]!=0 for k in ('provider_http_calls','database_writes','mapping_writes'))): fail('target_preflight_zero_authority')
    if (not isinstance(data['captured_at_utc'],str) or not re.fullmatch(r'[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z',data['captured_at_utc'])
            or receipt.get('captured_at_utc')!=data['captured_at_utc'] or type(data['schema_complete']) is not bool
            or receipt.get('schema_complete') is not data['schema_complete']): fail('target_preflight_status_binding')
    expected_columns={
        'catalog_hotels':{'id','name','country_id','country_name','region_name','subregion_name','category','is_active','latitude','longitude'},
        'tour_operator_identity_observations':{'hotel_id','last_seen_at'},
        'andromeda_hotel_identities':{'supplier_namespace','external_hotel_id','local_hotel_id','decision_status'},
        'anex_hotel_decisions':{'catalog_hotel_id'},
        'anex_review_pair_exclusions':{'catalog_hotel_id'},
    }
    missing=data['missing_columns']
    if not isinstance(missing,dict) or set(missing)!=set(expected_columns): fail('target_preflight_missing_shape')
    for table,columns in expected_columns.items():
        values=missing[table]
        if (not isinstance(values,list) or values!=list(dict.fromkeys(values))
                or any(not isinstance(value,str) or value not in columns for value in values)): fail('target_preflight_missing_shape')
    if data['schema_complete'] is (any(missing.values())): fail('target_preflight_schema_binding')
    metric_names={'cohort','invalid_coordinates','invalid_text','invalid_accepted_native','max_accepted_aliases','manual_targets','excluded_targets'}
    status=data['query_status'];metrics=data['metrics']
    if not isinstance(status,dict) or not isinstance(metrics,dict) or set(status)!=metric_names or set(metrics)!=metric_names: fail('target_preflight_metric_shape')
    for name in metric_names:
        if type(status[name]) is not bool: fail('target_preflight_metric_shape')
        value=metrics[name]
        if status[name]:
            if type(value) is not int or value<0: fail('target_preflight_metric_binding')
        elif value is not None: fail('target_preflight_metric_binding')
    if not data['schema_complete'] and any(status.values()): fail('target_preflight_schema_query_binding')

'''

REMOTE_TARGET_PREFLIGHT_DISPATCH = r'''    if mode=='match-tv-live30-target-preflight':
        result['match_tv_live30_target_preflight']=run_match_tv_live30_target_preflight(stage)
        result['supplier_calls']=0
        result['database_reads']=1
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''


REMOTE_TARGET_PREFLIGHT_READBACK_HANDLER = REMOTE_TARGET_PREFLIGHT_HANDLER + r'''
def run_match_tv_live30_target_preflight_readback(stage):
    original='int-andromeda-match-live30-target-preflight-20261001-v1'
    original_source='12ee0d4961db14a0a1bcddcd41229e5c6dff9aa5'
    if (operation!='int-andromeda-match-live30-target-preflight-readback-20261001-v1'
            or payload.get('batch')!='tv-live30-target-preflight-20261001'
            or payload.get('maximum_writes')!=0 or payload.get('provider_http_calls')!=0): fail('target_preflight_readback_fixed_scope')
    root=home/'.anytoour-match/operations';evidence=root/original
    if (not root.is_dir() or root.is_symlink() or root.resolve()!=root or not evidence.is_dir()
            or evidence.is_symlink() or evidence.resolve()!=evidence): fail('target_preflight_readback_private_root')
    reservation=safe_json(evidence/'reservation.json',65536)
    fixed={'operation':original,'source_sha':original_source,'batch':'tv-live30-target-preflight-20261001',
           'maximum_writes':0,'provider_http_calls':0,'state':'reserved_before_db_read'}
    if any(reservation.get(k)!=v for k,v in fixed.items()): fail('target_preflight_readback_reservation_binding')
    child=root/operation
    if child.exists() or child.is_symlink(): fail('target_preflight_readback_exists_no_replay')
    child.mkdir(mode=0o700)
    def exclusive(path,data):
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as stream:
            stream.write(json.dumps(data,sort_keys=True,separators=(',',':'),ensure_ascii=False).encode()+b'\n');stream.flush();os.fsync(stream.fileno())
    exclusive(child/'reservation.json',{'operation':operation,'source_sha':source,'evidence_operation':original,'no_replay':True})
    observed={}
    for name,cap in (('execution-started.json',65536),('result.json',262144),('receipt.json',65536)):
        path=evidence/name;exists=path.exists() or path.is_symlink()
        if exists and not safe_file(path,cap): fail('target_preflight_readback_file_shape')
        observed[name]={'present':exists,'bytes':path.stat().st_size if exists else 0,
                        'sha256':hashlib.sha256(path.read_bytes()).hexdigest() if exists else None}
    terminal=observed['result.json']['present'] and observed['receipt.json']['present']
    output={'state':'completed_saved_target_preflight_readback','operation':operation,'source_sha':source,
            'batch':'tv-live30-target-preflight-20261001','evidence_operation':original,'evidence_source_sha':original_source,
            'original_read_reexecuted':False,'provider_http_calls':0,'database_reads':0,'database_writes':0,
            'mapping_writes':0,'safe_to_write_now':False,'no_replay':True,'terminal_verified':False,
            'files':observed,'preflight':None}
    if terminal:
        data=safe_json(evidence/'result.json',262144);receipt=safe_json(evidence/'receipt.json',65536)
        validate_match_tv_live30_target_preflight(data,receipt,observed['result.json']['sha256'],original,original_source)
        output['terminal_verified']=True;output['preflight']=data
    exclusive(child/'result.json',output);return output
'''

REMOTE_TARGET_PREFLIGHT_READBACK_DISPATCH = r'''    if mode=='match-tv-live30-target-preflight-readback':
        result['match_tv_live30_target_preflight_readback']=run_match_tv_live30_target_preflight_readback(stage)
        result['supplier_calls']=0
        result['database_reads']=0
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''


REMOTE_TARGET_V2_HANDLER = r'''
def run_match_tv_live30_target_catalog_v2(stage):
    if (operation!='int-andromeda-match-live30-target-catalog-v2-20261001-v1'
            or payload.get('batch')!='tv-live30-targets-v2-20261001'
            or payload.get('maximum_writes')!=0 or payload.get('provider_http_calls')!=0): fail('target_catalog_v2_fixed_scope')
    root=home/'.anytoour-match/operations'
    if not root.is_dir() or root.is_symlink() or root.resolve()!=root: fail('target_catalog_v2_private_root')
    child=root/operation
    if child.exists() or child.is_symlink(): fail('target_catalog_v2_exists_no_replay')
    child.mkdir(mode=0o700)
    reservation={'operation':operation,'source_sha':source,'batch':'tv-live30-targets-v2-20261001',
                 'maximum_writes':0,'provider_http_calls':0,'state':'reserved_before_db_read','reserved_at':int(time.time())}
    fd=os.open(child/'reservation.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
    with os.fdopen(fd,'wb') as stream:
        stream.write(json.dumps(reservation,sort_keys=True,separators=(',',':')).encode()+b'\n');stream.flush();os.fsync(stream.fileno())
    runner=stage/'scripts/diagnostics/hotel_match_tv_live30_target_catalog_v2.php'
    if not safe_file(runner,2*1024*1024): fail('target_catalog_v2_runner_missing_no_replay')
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(child),'MATCH_SOURCE_SHA':source})
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0','-d','disable_functions='+disabled,
                        str(runner),'--current-targets-v2'],cwd=project,env=env,capture_output=True,text=True,timeout=240)
    if run.returncode!=0 or run.stderr.strip() or len(run.stdout.encode())>8388608: fail('target_catalog_v2_read_failed_no_replay')
    result_path=child/'result.json';receipt_path=child/'receipt.json'
    if not safe_file(result_path,8388608) or not safe_file(receipt_path,65536): fail('target_catalog_v2_terminal_missing_no_replay')
    data=safe_json(result_path,8388608);receipt=safe_json(receipt_path,65536);digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    if json.loads(run.stdout)!=data: fail('target_catalog_v2_stdout_binding')
    validate_match_tv_live30_target_catalog_v2(data,receipt,digest,operation,source)
    return {'result_sha256':digest,'summary':data}

def validate_match_tv_live30_target_catalog_v2(data,receipt,digest,expected_operation,expected_source):
    fixed={'state':'completed_tv_live30_target_catalog_v2','operation':expected_operation,'source_sha':expected_source,
           'batch':'tv-live30-targets-v2-20261001','provider_http_calls':0,'database_reads':1,'database_writes':0,
           'mapping_writes':0,'safe_to_write_now':False,'no_replay':True}
    fields=set(fixed)|{'captured_at_utc','cohort_count','row_count','held_count','rows','held'}
    if (not isinstance(data,dict) or not isinstance(receipt,dict) or set(data)!=fields
            or set(receipt)!=(fields-{'rows','held'})|{'result_sha256'}
            or any(data.get(k)!=v or receipt.get(k)!=v for k,v in fixed.items())
            or receipt.get('result_sha256')!=digest): fail('target_catalog_v2_terminal_binding')
    for value in (data,receipt):
        if (value.get('safe_to_write_now') is not False or value.get('no_replay') is not True
                or type(value.get('database_reads')) is not int or value['database_reads']!=1
                or any(type(value[k]) is not int or value[k]!=0 for k in ('provider_http_calls','database_writes','mapping_writes'))): fail('target_catalog_v2_zero_authority')
    if (not isinstance(data['captured_at_utc'],str) or not re.fullmatch(r'[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z',data['captured_at_utc'])
            or receipt.get('captured_at_utc')!=data['captured_at_utc']): fail('target_catalog_v2_timestamp')
    for name in ('cohort_count','row_count','held_count'):
        if type(data[name]) is not int or not 0<=data[name]<=20000 or receipt.get(name)!=data[name]: fail('target_catalog_v2_count_binding')
    if (data['row_count']+data['held_count']!=data['cohort_count'] or not isinstance(data['rows'],list)
            or len(data['rows'])!=data['row_count'] or not isinstance(data['held'],list) or len(data['held'])!=data['held_count']): fail('target_catalog_v2_partition')
    seen=set();row_fields={'id','name','country_id','country_name','region_name','subregion_name','category','is_active',
                          'latitude','longitude','accepted_samo_ids','manual_hold','exclusion_hold'}
    for row in data['rows']:
        if (not isinstance(row,dict) or set(row)!=row_fields or type(row['id']) is not int or row['id']<=0
                or row['id'] in seen or row['is_active'] is not True): fail('target_catalog_v2_row_identity')
        seen.add(row['id'])
        for key in ('name','country_id','country_name','region_name','subregion_name','category'):
            value=row[key]
            if value is not None and (not isinstance(value,str) or len(value.encode())>512 or re.search(r'[\x00-\x1f\x7f]|https?://',value,re.I)): fail('target_catalog_v2_row_text')
        if not row['name']: fail('target_catalog_v2_row_name')
        for key,bound in (('latitude',90),('longitude',180)):
            value=row[key]
            if value is not None and (type(value) not in (int,float) or not -bound<=value<=bound): fail('target_catalog_v2_row_coordinates')
        if any(type(row[key]) is not bool for key in ('manual_hold','exclusion_hold')): fail('target_catalog_v2_row_bool')
        occupants=row['accepted_samo_ids']
        if (not isinstance(occupants,list) or len(occupants)>32 or occupants!=sorted(set(occupants))
                or any(not isinstance(value,str) or not re.fullmatch(r'[1-9][0-9]{0,31}',value) for value in occupants)): fail('target_catalog_v2_row_occupants')
    allowed={'invalid_coordinates','invalid_text','invalid_accepted_native','accepted_alias_cap'}
    for hold in data['held']:
        if (not isinstance(hold,dict) or set(hold)!={'id','reasons'} or type(hold['id']) is not int or hold['id']<=0
                or hold['id'] in seen or not isinstance(hold['reasons'],list) or not hold['reasons']
                or hold['reasons']!=sorted(set(hold['reasons'])) or any(reason not in allowed for reason in hold['reasons'])): fail('target_catalog_v2_hold_shape')
        seen.add(hold['id'])

'''

REMOTE_TARGET_V2_DISPATCH = r'''    if mode=='match-tv-live30-target-catalog-v2':
        result['match_tv_live30_target_catalog_v2']=run_match_tv_live30_target_catalog_v2(stage)
        result['supplier_calls']=0
        result['database_reads']=1
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''


REMOTE_TARGET_READBACK_HANDLER = REMOTE_TARGET_HANDLER + r'''
def run_match_tv_live30_target_readback(stage):
    original='int-andromeda-match-live30-target-catalog-20261001-v1'
    original_source='6b49c5ac61ca21e7bb413d30d6badd9f29518cb4'
    if (operation!='int-andromeda-match-live30-target-readback-20261001-v1'
            or payload.get('batch')!='tv-live30-targets-20261001'
            or payload.get('maximum_writes')!=0 or payload.get('provider_http_calls')!=0): fail('target_readback_fixed_scope')
    root=home/'.anytoour-match/operations';evidence=root/original
    if (not root.is_dir() or root.is_symlink() or root.resolve()!=root or not evidence.is_dir()
            or evidence.is_symlink() or evidence.resolve()!=evidence): fail('target_readback_private_root')
    reservation=safe_json(evidence/'reservation.json',65536)
    fixed={'operation':original,'source_sha':original_source,'batch':'tv-live30-targets-20261001',
           'maximum_writes':0,'provider_http_calls':0,'state':'reserved_before_db_read'}
    if any(reservation.get(k)!=v for k,v in fixed.items()): fail('target_readback_reservation_binding')
    child=root/operation
    if child.exists() or child.is_symlink(): fail('target_readback_exists_no_replay')
    child.mkdir(mode=0o700)
    def exclusive(path,data):
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as stream:
            stream.write(json.dumps(data,sort_keys=True,separators=(',',':'),ensure_ascii=False).encode()+b'\n');stream.flush();os.fsync(stream.fileno())
    exclusive(child/'reservation.json',{'operation':operation,'source_sha':source,'evidence_operation':original,'no_replay':True})
    observed={}
    for name,cap in (('execution-started.json',65536),('result.json',8388608),('receipt.json',65536)):
        path=evidence/name;exists=path.exists() or path.is_symlink()
        if exists and not safe_file(path,cap): fail('target_readback_file_shape')
        observed[name]={'present':exists,'bytes':path.stat().st_size if exists else 0,
                        'sha256':hashlib.sha256(path.read_bytes()).hexdigest() if exists else None}
    terminal=observed['result.json']['present'] and observed['receipt.json']['present']
    output={'state':'completed_saved_target_catalog_readback','operation':operation,'source_sha':source,'batch':'tv-live30-targets-20261001',
            'evidence_operation':original,'evidence_source_sha':original_source,'original_read_reexecuted':False,
            'provider_http_calls':0,'database_reads':0,'database_writes':0,'mapping_writes':0,'safe_to_write_now':False,
            'no_replay':True,'terminal_verified':False,'files':observed,'catalog':None}
    if terminal:
        data=safe_json(evidence/'result.json',8388608);receipt=safe_json(evidence/'receipt.json',65536)
        validate_match_tv_live30_target_catalog(data,receipt,observed['result.json']['sha256'],original,original_source)
        output['terminal_verified']=True;output['catalog']=data
    exclusive(child/'result.json',output);return output
'''

REMOTE_TARGET_READBACK_DISPATCH = r'''    if mode=='match-tv-live30-target-readback':
        result['match_tv_live30_target_readback']=run_match_tv_live30_target_readback(stage)
        result['supplier_calls']=0
        result['database_reads']=0
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''




REMOTE_INTOURIST4_READBACK_HANDLER = r'''
def run_match_intourist4_readback(stage):
    expected_source='a82516771252fab0ac4bd079480156c684766e05'
    source_operation='int-tourvisor-match-intourist4-selectors-readonly-20261001-v1'
    source_batch='intourist4-official-context-20261001'
    if (operation!='int-tourvisor-match-intourist4-selectors-readback-20261004-v1'
            or payload.get('batch')!='intourist4-terminal-readback-20261004'
            or source!=expected_source
            or type(payload.get('maximum_writes')) is not int or payload['maximum_writes']!=0
            or type(payload.get('provider_http_calls')) is not int or payload['provider_http_calls']!=0):
        fail('intourist4_readback_fixed_scope')
    parent=home/'.anytoour-match';root=parent/'operations'
    for folder in (parent,root):
        if not folder.is_dir() or folder.is_symlink() or folder.resolve()!=folder:
            fail('intourist4_readback_private_root')
    source_child=root/source_operation
    if not source_child.is_dir() or source_child.is_symlink() or source_child.resolve()!=source_child:
        fail('intourist4_readback_source_missing')
    batch_marker=parent/'intourist4-selectors-batch-intourist4-official-context-20261001.json'
    source_reservation=source_child/'reservation.json'
    if not safe_file(batch_marker,65536) or not safe_file(source_reservation,65536):
        fail('intourist4_readback_source_reservation')
    batch_data=safe_json(batch_marker,65536);reservation=safe_json(source_reservation,65536)
    expected_reservation={'operation':source_operation,'source_sha':expected_source,'batch':source_batch,
                          'maximum_writes':0,'provider_http_calls':14,'state':'reserved_before_db_and_provider'}
    for name,data in (('batch',batch_data),('reservation',reservation)):
        if (not isinstance(data,dict) or any(data.get(k)!=v for k,v in expected_reservation.items())
                or type(data.get('reserved_at')) is not int or data['reserved_at']<1):
            fail('intourist4_readback_'+name+'_binding')
    readback_child=root/operation
    if readback_child.exists() or readback_child.is_symlink():
        fail('intourist4_readback_child_exists_no_replay')
    own={'operation':operation,'source_sha':source,'batch':'intourist4-terminal-readback-20261004',
         'maximum_writes':0,'provider_http_calls':0,'source_operation':source_operation,
         'state':'reserved_readback_only','reserved_at':int(time.time())}
    def exclusive(path,value):
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as stream:
            stream.write(json.dumps(value,sort_keys=True,separators=(',',':')).encode()+b'\n')
            stream.flush();os.fsync(stream.fileno())
        fd=os.open(path.parent,os.O_RDONLY|os.O_DIRECTORY)
        try: os.fsync(fd)
        finally: os.close(fd)
    exclusive(parent/'intourist4-selectors-readback-batch-intourist4-terminal-readback-20261004.json',own)
    readback_child.mkdir(mode=0o700);exclusive(readback_child/'reservation.json',own)

    errors=[]
    def optional_json(path,limit,label):
        if not path.exists():
            errors.append(label+'_missing');return None,None
        if not safe_file(path,limit):
            fail('intourist4_readback_'+label+'_unsafe')
        raw=path.read_bytes();return safe_json(path,limit),hashlib.sha256(raw).hexdigest()
    result,result_sha=optional_json(source_child/'result.json',8*1024*1024,'result')
    receipt,receipt_sha=optional_json(source_child/'receipt.json',65536,'receipt')
    allowed_states={'completed_read_only','failed_before_provider_access','terminal_failed_no_replay'}
    allowed_group_states={'preflight_hold','completed_read_only','current_rows_hold','current_rows_changed'}
    allowed_edge_states={'operator_tour_returned','detail_404','detail_identity_mismatch',
                         'detail_identity_verified','current_hold_before_detail'}
    allowed_links={'missing','invalid','invalid_origin','unexpected_intourist_host','secret_bearing_link',
                   'captured_single_native','captured_ambiguous_native','missing_native','not_read'}
    allowed_calls={'search_start','search_status','search_results','tour_detail'}
    def safe_int(data,key,limit,label):
        value=data.get(key) if isinstance(data,dict) else None
        if type(value) is int and 0<=value<=limit:return value
        errors.append(label);return None
    def safe_bool(data,key,label):
        value=data.get(key) if isinstance(data,dict) else None
        if type(value) is bool:return value
        errors.append(label);return None
    result_state=None;reason_sha=None;call_counts={};edge_counts={};groups=[]
    counters={k:None for k in ('provider_http_calls','physical_http_attempts','database_reads',
                                'database_writes','mapping_writes','returned_edges')}
    result_no_replay=None;result_safe=None
    if result is not None:
        if not isinstance(result,dict):
            errors.append('result_not_object')
        else:
            state=result.get('state')
            if state in allowed_states:result_state=state
            else:errors.append('result_state')
            reason=result.get('reason')
            if reason is None:reason_sha=None
            elif isinstance(reason,str) and len(reason)<=4096:
                reason_sha=hashlib.sha256(reason.encode()).hexdigest()
            else:errors.append('result_reason')
            limits={'provider_http_calls':14,'physical_http_attempts':14,'database_reads':32,
                    'database_writes':0,'mapping_writes':0,'returned_edges':4}
            for key,limit in limits.items():counters[key]=safe_int(result,key,limit,'result_'+key)
            result_no_replay=safe_bool(result,'no_replay','result_no_replay')
            result_safe=safe_bool(result,'safe_to_write_now','result_safe_to_write_now')
            raw_calls=result.get('call_counts')
            if isinstance(raw_calls,dict):
                for key,value in raw_calls.items():
                    if key in allowed_calls and type(value) is int and 0<=value<=14:call_counts[key]=value
                    else:errors.append('result_call_counts');call_counts={};break
            else:errors.append('result_call_counts')
            raw_counts=result.get('edge_state_counts')
            if isinstance(raw_counts,dict):
                for key,value in raw_counts.items():
                    if key in allowed_edge_states and type(value) is int and 0<=value<=4:edge_counts[key]=value
                    else:errors.append('result_edge_state_counts');edge_counts={};break
            else:errors.append('result_edge_state_counts')
            raw_groups=result.get('groups')
            if not isinstance(raw_groups,list) or len(raw_groups)>2:
                errors.append('result_groups')
            else:
                for group in raw_groups:
                    if not isinstance(group,dict):
                        errors.append('result_group_shape');continue
                    gnum=group.get('group');country=group.get('country_id');gstate=group.get('state')
                    sent=group.get('sent');edges=group.get('edges')
                    if (type(gnum) is not int or gnum not in (1,2) or country not in (1,4)
                            or gstate not in allowed_group_states or type(sent) is not int or not 0<=sent<=2
                            or not isinstance(edges,list) or len(edges)>2):
                        errors.append('result_group_shape');continue
                    clean_edges=[]
                    for edge in edges:
                        if not isinstance(edge,dict):
                            errors.append('result_edge_shape');continue
                        source_id=edge.get('source_catalog_id');target=edge.get('target_tv_hotel_id')
                        estate=edge.get('state');operator_id=edge.get('operator_id');namespace=edge.get('namespace')
                        link=edge.get('link_state','not_read');ids=edge.get('positive_native_candidates',[])
                        match=edge.get('matches_source_native')
                        if (source_id not in ('2000034121','2000062084','2000052591','2000073045')
                                or type(target) is not int or target not in (1151,70943,128,80964)
                                or estate not in allowed_edge_states or operator_id!=43 or namespace!='operator_342'
                                or link not in allowed_links or not isinstance(ids,list) or len(ids)>8
                                or any(type(v) is not int or v<1 for v in ids)
                                or (match is not None and type(match) is not bool)):
                            errors.append('result_edge_shape');continue
                        clean_edges.append({'source_catalog_id':source_id,'target_tv_hotel_id':target,
                                            'state':estate,'operator_id':43,'namespace':'operator_342',
                                            'link_state':link,'native_candidate_count':len(ids),
                                            'matches_source_native':match})
                    groups.append({'group':gnum,'country_id':country,'state':gstate,'sent':sent,
                                   'edge_count':len(edges),'edges':clean_edges})
    receipt_state=None;receipt_counters={k:None for k in ('provider_http_calls','database_reads','database_writes','mapping_writes')}
    receipt_no_replay=None;receipt_safe=None
    if receipt is not None:
        if not isinstance(receipt,dict):
            errors.append('receipt_not_object')
        else:
            state=receipt.get('state')
            if state in allowed_states:receipt_state=state
            else:errors.append('receipt_state')
            for key,limit in {'provider_http_calls':14,'database_reads':32,'database_writes':0,'mapping_writes':0}.items():
                receipt_counters[key]=safe_int(receipt,key,limit,'receipt_'+key)
            receipt_no_replay=safe_bool(receipt,'no_replay','receipt_no_replay')
            receipt_safe=safe_bool(receipt,'safe_to_write_now','receipt_safe_to_write_now')
            if receipt.get('operation')!=source_operation or receipt.get('batch')!=source_batch or receipt.get('source_sha')!=expected_source:
                errors.append('receipt_binding')
            if result_sha is not None and receipt.get('result_sha256')!=result_sha:
                errors.append('receipt_result_digest')
    summary={'schema':'match-intourist4-selectors-readback-result/1','operation':operation,
             'batch':'intourist4-terminal-readback-20261004','source_sha':source,
             'source_operation':source_operation,'source_batch':source_batch,
             'state':'completed_read_only','provider_http_calls':0,'database_writes':0,'mapping_writes':0,
             'safe_to_write_now':False,'no_replay':True,
             'source_batch_sha256':hashlib.sha256(batch_marker.read_bytes()).hexdigest(),
             'source_reservation_sha256':hashlib.sha256(source_reservation.read_bytes()).hexdigest(),
             'source_result_present':result is not None,'source_result_sha256':result_sha,
             'source_receipt_present':receipt is not None,'source_receipt_sha256':receipt_sha,
             'source_result_state':result_state,'source_receipt_state':receipt_state,
             'source_result_reason_sha256':reason_sha,'source_result_counters':counters,
             'source_receipt_counters':receipt_counters,'source_result_no_replay':result_no_replay,
             'source_receipt_no_replay':receipt_no_replay,'source_result_safe_to_write_now':result_safe,
             'source_receipt_safe_to_write_now':receipt_safe,'source_call_counts':call_counts,
             'source_edge_state_counts':edge_counts,'source_groups':groups,
             'shape_errors':sorted(set(errors))}
    result_bytes=json.dumps(summary,sort_keys=True,separators=(',',':')).encode()+b'\n'
    result_digest=hashlib.sha256(result_bytes).hexdigest()
    exclusive(readback_child/'result.json',summary)
    rb_receipt={'operation':operation,'batch':'intourist4-terminal-readback-20261004','source_sha':source,
                'state':'completed_read_only','result_sha256':result_digest,'provider_http_calls':0,
                'database_writes':0,'mapping_writes':0,'safe_to_write_now':False,'no_replay':True}
    exclusive(readback_child/'receipt.json',rb_receipt)
    return {'result_sha256':result_digest,'successful':True,'no_replay':True,'summary':summary}
'''

REMOTE_INTOURIST4_READBACK_DISPATCH = r'''    if mode=='match-intourist4-selectors-readback':
        lane=run_match_intourist4_readback(stage)
        result['match_intourist4_selectors_readback']=lane
        result['supplier_calls']=0
        result['database_reads']=0
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''

REMOTE_INTOURIST4_HANDLER = r'''
def validate_match_intourist4(data,receipt,digest,expected_source):
    expected={'2000034121':('549',1151,4),'2000062084':('18273',70943,4),
              '2000052591':('25728',128,1),'2000073045':('29363',80964,1)}
    fixed={'schema':'match-intourist4-selectors-readonly-result/1',
           'operation':'int-tourvisor-match-intourist4-selectors-readonly-20261001-v1',
           'batch':'intourist4-official-context-20261001','source_sha':expected_source,
           'requested_rows':4,'tourvisor_account':'TOURVISOR_ANEX_JWT','operator_ids':[43],
           'continue_calls':0,'dates_calls':0,'database_writes':0,'mapping_writes':0,
           'safe_to_write_now':False}
    extra={'state','reason','captured_at_utc','groups','preflight_snapshots','provider_http_calls',
           'physical_http_attempts','database_reads','call_counts','returned_edges',
           'edge_state_counts','no_replay'}
    receipt_fields={'operation','batch','source_sha','state','result_sha256','provider_http_calls',
                    'database_reads','database_writes','mapping_writes','safe_to_write_now','no_replay'}
    if (not isinstance(data,dict) or set(data)!=set(fixed)|extra
            or not isinstance(receipt,dict) or set(receipt)!=receipt_fields
            or any(data.get(k)!=v for k,v in fixed.items()) or receipt.get('result_sha256')!=digest
            or any(receipt.get(k)!=data.get(k) for k in receipt_fields-{'result_sha256'})):
        fail('intourist4_terminal_binding')
    ints=('provider_http_calls','physical_http_attempts','database_reads','returned_edges')
    if (any(type(data.get(k)) is not int for k in ints)
            or not 0<=data['provider_http_calls']<=14
            or data['physical_http_attempts']!=data['provider_http_calls']
            or not 0<=data['database_reads']<=16 or not 0<=data['returned_edges']<=4
            or type(data['no_replay']) is not bool or data['no_replay']!=(data['provider_http_calls']>0)):
        fail('intourist4_counter_authority')
    for k in ('provider_http_calls','database_reads','database_writes','mapping_writes'):
        if type(receipt.get(k)) is not int or receipt[k]!=data[k]: fail('intourist4_receipt_counter')
    if receipt['safe_to_write_now'] is not False or type(receipt['no_replay']) is not bool:
        fail('intourist4_receipt_authority')
    state=data['state'];success=state=='completed_read_only'
    if state not in ('completed_read_only','failed_before_provider_access','terminal_failed_no_replay'):
        fail('intourist4_terminal_state')
    if ((state=='failed_before_provider_access' and data['provider_http_calls']!=0)
            or (state=='terminal_failed_no_replay' and data['provider_http_calls']==0)
            or (success and data['reason'] is not None)):
        fail('intourist4_terminal_counts')
    reason=data['reason']
    if not success and (not isinstance(reason,str) or len(reason)<1 or len(reason)>180
            or re.search(r'[\x00-\x1f\x7f]',reason)): fail('intourist4_reason_shape')
    stamp=data['captured_at_utc']
    if not isinstance(stamp,str):
        fail('intourist4_timestamp')
    import datetime as dt
    try:
        parsed=dt.datetime.fromisoformat(stamp.replace('Z','+00:00'))
        if parsed.utcoffset()!=dt.timedelta(0): fail('intourist4_timestamp')
    except (ValueError,TypeError): fail('intourist4_timestamp')
    calls=data['call_counts']
    allowed_calls={'search_start','search_status','search_results','tour_detail'}
    if (not isinstance(calls,dict) or any(k not in allowed_calls or type(v) is not int or v<0 for k,v in calls.items())
            or sum(calls.values())!=data['provider_http_calls']
            or calls.get('search_start',0)>2 or calls.get('search_status',0)>6
            or calls.get('search_results',0)>2 or calls.get('tour_detail',0)>4):
        fail('intourist4_call_partition')
    holds={'current_source_not_pending_null','current_source_history_review','target_missing_or_inactive',
           'target_country_changed','target_manual','target_excluded','target_occupied','protected_source'}
    snapshots=data['preflight_snapshots']
    if not isinstance(snapshots,list) or len(snapshots)!=data['database_reads']:
        fail('intourist4_preflight_count')
    last=0
    for snap in snapshots:
        if (not isinstance(snap,dict) or set(snap)!={'sequence','next_http_call','action','rows'}
                or type(snap['sequence']) is not int or snap['sequence']!=last+1
                or type(snap['next_http_call']) is not int or not 1<=snap['next_http_call']<=15
                or not isinstance(snap['action'],str) or not isinstance(snap['rows'],list)
                or not 1<=len(snap['rows'])<=2):
            fail('intourist4_preflight_shape')
        last=snap['sequence'];seen=set()
        for row in snap['rows']:
            if (not isinstance(row,dict) or set(row)!={'source_catalog_id','target_tv_hotel_id','state','holds','safe_to_write_now'}
                    or row['source_catalog_id'] not in expected or row['safe_to_write_now'] is not False):
                fail('intourist4_preflight_row')
            native,target,country=expected[row['source_catalog_id']]
            if type(row['target_tv_hotel_id']) is not int or row['target_tv_hotel_id']!=target:
                fail('intourist4_preflight_identity')
            hs=row['holds']
            if (not isinstance(hs,list) or hs!=sorted(set(hs)) or any(h not in holds for h in hs)
                    or row['state']!=('eligible' if not hs else 'hold') or target in seen):
                fail('intourist4_preflight_state')
            seen.add(target)
    groups=data['groups']
    if not isinstance(groups,list) or len(groups)>2:
        fail('intourist4_groups')
    group_countries=set();edge_count=0;edge_states={}
    edge_base={'source_catalog_id','source_native_id','target_tv_hotel_id','operator_id','namespace',
               'operator_tour_count','tour_id_sha256','state','safe_to_write_now'}
    edge_optional={'tour_detail_http','operator_link_sha256','operator_link_host','positive_native_candidates',
                   'query_keys','link_state','matches_source_native'}
    group_base={'group','country_id','state','sent','edges'}
    group_optional={'initial_preflight','search_complete','returned_targets'}
    allowed_group_states={'preflight_hold','completed_read_only','current_rows_hold','current_rows_changed'}
    allowed_edge_states={'operator_tour_returned','detail_404','detail_identity_mismatch',
                         'detail_identity_verified','current_hold_before_detail'}
    allowed_links={'missing','invalid','invalid_origin','unexpected_intourist_host','secret_bearing_link',
                   'captured_single_native','captured_ambiguous_native','missing_native','not_read'}
    seen_targets=set()
    for group in groups:
        if (not isinstance(group,dict) or not group_base.issubset(group)
                or set(group)-group_base-group_optional or type(group['group']) is not int
                or group['group'] not in (1,2) or group['country_id'] not in (1,4)
                or group['country_id'] in group_countries or group['state'] not in allowed_group_states
                or type(group['sent']) is not int or not 0<=group['sent']<=2
                or not isinstance(group['edges'],list)):
            fail('intourist4_group_shape')
        group_countries.add(group['country_id'])
        for edge in group['edges']:
            if (not isinstance(edge,dict) or not edge_base.issubset(edge)
                    or set(edge)-edge_base-edge_optional or edge['source_catalog_id'] not in expected
                    or edge['source_native_id']!=expected[edge['source_catalog_id']][0]
                    or type(edge['target_tv_hotel_id']) is not int
                    or edge['target_tv_hotel_id']!=expected[edge['source_catalog_id']][1]
                    or expected[edge['source_catalog_id']][2]!=group['country_id']
                    or edge['operator_id']!=43 or edge['namespace']!='operator_342'
                    or type(edge['operator_tour_count']) is not int or edge['operator_tour_count']<1
                    or not isinstance(edge['tour_id_sha256'],str) or not re.fullmatch(r'[0-9a-f]{64}',edge['tour_id_sha256'])
                    or edge['state'] not in allowed_edge_states or edge['safe_to_write_now'] is not False
                    or edge['target_tv_hotel_id'] in seen_targets):
                fail('intourist4_edge_identity')
            seen_targets.add(edge['target_tv_hotel_id']);edge_count+=1
            edge_states[edge['state']]=edge_states.get(edge['state'],0)+1
            if 'positive_native_candidates' in edge:
                ids=edge['positive_native_candidates']
                if (not isinstance(ids,list) or ids!=sorted(set(ids))
                        or any(type(v) is not int or v<1 for v in ids)): fail('intourist4_native_candidates')
            if 'link_state' in edge and edge['link_state'] not in allowed_links:
                fail('intourist4_link_state')
            if edge.get('link_state') in ('captured_single_native','captured_ambiguous_native'):
                host=edge.get('operator_link_host')
                if not isinstance(host,str) or not (host=='intourist.ru' or host.endswith('.intourist.ru')):
                    fail('intourist4_link_host')
            if 'matches_source_native' in edge:
                ids=edge.get('positive_native_candidates',[])
                expected_match=ids==[int(edge['source_native_id'])]
                if type(edge['matches_source_native']) is not bool or edge['matches_source_native']!=expected_match:
                    fail('intourist4_native_match')
    if edge_count!=data['returned_edges'] or data['edge_state_counts']!=edge_states:
        fail('intourist4_edge_partition')
    summary={k:v for k,v in data.items() if k!='reason'}
    summary['reason_sha256']=hashlib.sha256(reason.encode()).hexdigest() if reason else None
    return summary

def run_match_intourist4(stage):
    if (operation!='int-tourvisor-match-intourist4-selectors-readonly-20261001-v1'
            or payload.get('batch')!='intourist4-official-context-20261001'
            or type(payload.get('maximum_writes')) is not int or payload['maximum_writes']!=0
            or type(payload.get('provider_http_calls')) is not int or payload['provider_http_calls']!=14):
        fail('intourist4_fixed_scope')
    manifest=stage/'scripts/diagnostics/fixtures/hotel_match_intourist4_selectors_readonly_v1.json'
    runner=stage/'scripts/diagnostics/hotel_match_intourist4_selectors_readonly_v1.py'
    if (not safe_file(runner,2*1024*1024) or not safe_file(manifest,65536)
            or hashlib.sha256(manifest.read_bytes()).hexdigest()!='3f05ddb13707866e8b3442da61528a1713b0ac710b53840a8b43ef5181337778'):
        fail('intourist4_source_binding')
    for path in ('reports/hotel-match-intourist-official-context-20261001.json',
                 'reports/hotel-match-minimal-nonbg-ledger-reconcile-20261001.json'):
        if not safe_file(stage/path,2*1024*1024): fail('intourist4_source_binding')
    parent=home/'.anytoour-match';root=parent/'operations'
    for folder in (parent,root):
        if not folder.is_dir() or folder.is_symlink() or folder.resolve()!=folder: fail('intourist4_private_root')
    child=root/operation
    if child.exists() or child.is_symlink(): fail('intourist4_child_exists_no_replay')
    reservation={'operation':operation,'source_sha':source,'batch':'intourist4-official-context-20261001',
                 'maximum_writes':0,'provider_http_calls':14,'state':'reserved_before_db_and_provider',
                 'reserved_at':int(time.time())}
    def exclusive(path,value):
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as stream:
            stream.write(json.dumps(value,sort_keys=True,separators=(',',':')).encode()+b'\n')
            stream.flush();os.fsync(stream.fileno())
        fd=os.open(path.parent,os.O_RDONLY|os.O_DIRECTORY)
        try: os.fsync(fd)
        finally: os.close(fd)
    exclusive(parent/'intourist4-selectors-batch-intourist4-official-context-20261001.json',reservation)
    child.mkdir(mode=0o700);exclusive(child/'reservation.json',reservation)
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_SOURCE_ROOT':str(stage),
                'MATCH_OPERATION_DIR':str(child),'MATCH_MANIFEST_PATH':str(manifest),
                'MATCH_SOURCE_SHA':source})
    run=subprocess.run(['python3',str(runner),'--execute'],cwd=project,env=env,
                       capture_output=True,text=True,timeout=300)
    result_path=child/'result.json';receipt_path=child/'receipt.json'
    if (not safe_file(result_path,8*1024*1024) or not safe_file(receipt_path,65536)
            or run.stderr.strip() or len(run.stdout.encode())>65536):
        fail('intourist4_terminal_missing_no_replay')
    data=safe_json(result_path,8*1024*1024);receipt=safe_json(receipt_path,65536)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    summary=validate_match_intourist4(data,receipt,digest,source)
    successful=summary['state']=='completed_read_only'
    if run.returncode!=(0 if successful else 2): fail('intourist4_exit_binding')
    stdout=json.loads(run.stdout)
    if stdout!={k:data[k] for k in ('state','reason','requested_rows','provider_http_calls','returned_edges','edge_state_counts')}:
        fail('intourist4_stdout_binding')
    return {'result_sha256':digest,'successful':successful,'no_replay':True,'summary':summary}
'''

REMOTE_INTOURIST4_DISPATCH = r'''    if mode=='match-intourist4-selectors-readonly':
        lane=run_match_intourist4(stage)
        result['match_intourist4_selectors_readonly']=lane
        result['supplier_calls']=lane['summary']['provider_http_calls']
        result['database_reads']=lane['summary']['database_reads']
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete' if lane['successful'] else 'terminal_nonzero_no_replay'
'''


REMOTE_BP8_HANDLER = r'''
def validate_match_bg8_pins(data,receipt,digest,input_digest,expected_source,validate_source):
    fixed={'schema':'match-bg8-pin-bindings-readonly-result/1',
           'operation':'int-andromeda-match-bg8-pin-bindings-readonly-20261004-v1',
           'batch':'bg8-pin-bindings-20261004','source_sha':expected_source,
           'provider_http_calls':0,'physical_http_attempts':0,'database_writes':0,
           'mapping_writes':0,'booking_calls':0,'lead_calls':0,'accepted':0,'written':0,
           'safe_to_write_now':False,'acceptance_evaluated':False,
           'global_uniqueness_evaluated':False,'no_replay':True,
           'operator_ids':[18],'requested_rows':8}
    if (not isinstance(data,dict) or not isinstance(receipt,dict)
            or any(type(data.get(k)) is not type(v) or data.get(k)!=v for k,v in fixed.items())
            or type(data.get('database_reads')) is not int or data['database_reads']!=0
            or data.get('private_input_sha256')!=input_digest
            or receipt.get('private_input_sha256')!=input_digest
            or receipt.get('result_sha256')!=digest):fail('bg8_pins_terminal_binding')
    for k,v in receipt.items():
        if k not in ('result_sha256','private_input_sha256') and (k not in data or type(v) is not type(data[k]) or v!=data[k]):fail('bg8_pins_receipt_binding')
    if set(receipt)!={'operation','batch','source_sha','state','result_sha256','private_input_sha256',
                     'provider_http_calls','physical_http_attempts','database_reads','database_writes',
                     'mapping_writes','booking_calls','lead_calls','accepted','written',
                     'safe_to_write_now','acceptance_evaluated','global_uniqueness_evaluated','no_replay'}:fail('bg8_pins_receipt_shape')
    try:validate_source(data)
    except Exception:fail('bg8_pins_source_validation')
    if data['state'] not in ('completed_read_only_bg8_pins','completed_read_only_bg8_pins_incomplete','terminal_failed_no_replay'):fail('bg8_pins_terminal_state')
    return data

def run_match_bg8_pins(stage):
    if (operation!='int-andromeda-match-bg8-pin-bindings-readonly-20261004-v1'
            or payload.get('batch')!='bg8-pin-bindings-20261004'
            or type(payload.get('maximum_writes')) is not int or payload['maximum_writes']!=0
            or type(payload.get('provider_http_calls')) is not int or payload['provider_http_calls']!=0):fail('bg8_pins_scope')
    runner=stage/'scripts/diagnostics/hotel_match_bg8_pin_bindings_readonly_v1.py'
    manifest=stage/'scripts/diagnostics/fixtures/hotel_match_bg8_pin_bindings_readonly_v1.json'
    if (not safe_file(runner,2*1024*1024) or not safe_file(manifest,65536)
            or hashlib.sha256(manifest.read_bytes()).hexdigest()!='4a14f5b4c641d80e378e1358341da09de17dc35478087bff134f63879c14ec1c'):fail('bg8_pins_source_binding')
    parent=home/'.anytoour-match';root=parent/'operations'
    for folder in (parent,root):
        if not folder.is_dir() or folder.is_symlink() or folder.resolve()!=folder:fail('bg8_pins_private_root')
    child=root/operation
    if child.exists() or child.is_symlink():fail('bg8_pins_child_exists_no_replay')
    reservation={'operation':operation,'source_sha':source,'batch':'bg8-pin-bindings-20261004',
                 'provider_http_calls':0,'maximum_writes':0,'state':'reserved_before_retained_read'}
    def exclusive(path,value):
        raw=json.dumps(value,sort_keys=True,separators=(',',':')).encode()+b'\n'
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as stream:
            if stream.write(raw)!=len(raw):fail('bg8_pins_reservation_short_write')
            stream.flush();os.fsync(stream.fileno())
        fd=os.open(path.parent,os.O_RDONLY|os.O_DIRECTORY)
        try:os.fsync(fd)
        finally:os.close(fd)
        if path.read_bytes()!=raw:fail('bg8_pins_reservation_readback')
    exclusive(parent/'bg8-pin-bindings-batch-20261004.json',reservation)
    child.mkdir(mode=0o700)
    fd=os.open(root,os.O_RDONLY|os.O_DIRECTORY)
    try:os.fsync(fd)
    finally:os.close(fd)
    exclusive(child/'reservation.json',reservation)
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_SOURCE_ROOT':str(stage),
                'MATCH_OPERATION_DIR':str(child),'MATCH_MANIFEST_PATH':str(manifest),'MATCH_SOURCE_SHA':source})
    run=subprocess.run(['python3',str(runner),'--execute'],cwd=project,env=env,capture_output=True,text=True,timeout=300)
    result_path=child/'result.json';receipt_path=child/'receipt.json';input_path=child/'current-input.json'
    if (not safe_file(result_path,2*1024*1024) or not safe_file(receipt_path,65536)
            or not safe_file(input_path,2*1024*1024) or run.stderr.strip()
            or len(run.stdout.encode())>65536):fail('bg8_pins_terminal_missing_no_replay')
    import importlib.util
    spec=importlib.util.spec_from_file_location('checked_bg8_pins_source',runner)
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
    data=safe_json(result_path,2*1024*1024);receipt=safe_json(receipt_path,65536)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    input_digest=hashlib.sha256(input_path.read_bytes()).hexdigest()
    summary=validate_match_bg8_pins(data,receipt,digest,input_digest,source,module.validate_result)
    successful=summary['state'] in ('completed_read_only_bg8_pins','completed_read_only_bg8_pins_incomplete')
    if run.returncode!=(0 if successful else 2):fail('bg8_pins_exit_binding')
    stdout=json.loads(run.stdout)
    if stdout!={k:data[k] for k in ('state','rows_examined','accepted','written')}:fail('bg8_pins_stdout_binding')
    return {'result_sha256':digest,'private_input_sha256':input_digest,'successful':successful,'no_replay':True,'summary':summary}

'''

REMOTE_BP8_DISPATCH = r'''    if mode=='match-bg8-pin-bindings-readonly':
        lane=run_match_bg8_pins(stage)
        result['match_bg8_pin_bindings_readonly']=lane
        result['supplier_calls']=0
        result['database_reads']=lane['summary']['database_reads']
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete' if lane['successful'] else 'terminal_nonzero_no_replay'

'''

REMOTE_NF7_HANDLER = r'''
def validate_match_nonbg7_fields(data,receipt,digest,input_digest,expected_source,validate_source):
    fixed={'schema':'match-nonbg7-unexported-fields-readonly-result/1',
           'operation':'int-andromeda-match-nonbg7-unexported-fields-20261004-v1',
           'batch':'nonbg7-unexported-fields-20261004','source_sha':expected_source,
           'provider_http_calls':0,'physical_http_attempts':0,'database_writes':0,
           'mapping_writes':0,'booking_calls':0,'lead_calls':0,'accepted':0,'written':0,
           'safe_to_write_now':False,'acceptance_evaluated':False,
           'global_uniqueness_evaluated':False,'no_replay':True,
           'operator_ids':[13,25,43],'requested_rows':7,'requested_sources':6}
    if (not isinstance(data,dict) or not isinstance(receipt,dict)
            or any(type(data.get(k)) is not type(v) or data.get(k)!=v for k,v in fixed.items())
            or type(data.get('database_reads')) is not int or data['database_reads']!=0
            or data.get('private_input_sha256')!=input_digest
            or receipt.get('private_input_sha256')!=input_digest
            or receipt.get('result_sha256')!=digest):fail('nonbg7_fields_terminal_binding')
    for k,v in receipt.items():
        if k not in ('result_sha256','private_input_sha256') and (k not in data or type(v) is not type(data[k]) or v!=data[k]):fail('nonbg7_fields_receipt_binding')
    if set(receipt)!={'operation','batch','source_sha','state','result_sha256','private_input_sha256',
                     'provider_http_calls','physical_http_attempts','database_reads','database_writes',
                     'mapping_writes','booking_calls','lead_calls','accepted','written',
                     'safe_to_write_now','acceptance_evaluated','global_uniqueness_evaluated','no_replay'}:fail('nonbg7_fields_receipt_shape')
    try:validate_source(data)
    except Exception:fail('nonbg7_fields_source_validation')
    if data['state'] not in ('completed_read_only_nonbg7_fields','completed_read_only_nonbg7_fields_incomplete','terminal_failed_no_replay'):fail('nonbg7_fields_terminal_state')
    return data

def run_match_nonbg7_fields(stage):
    if (operation!='int-andromeda-match-nonbg7-unexported-fields-20261004-v1'
            or payload.get('batch')!='nonbg7-unexported-fields-20261004'
            or type(payload.get('maximum_writes')) is not int or payload['maximum_writes']!=0
            or type(payload.get('provider_http_calls')) is not int or payload['provider_http_calls']!=0):fail('nonbg7_fields_scope')
    runner=stage/'scripts/diagnostics/hotel_match_nonbg7_unexported_fields_readonly_v1.py'
    manifest=stage/'scripts/diagnostics/fixtures/hotel_match_nonbg7_unexported_fields_readonly_v1.json'
    if (not safe_file(runner,2*1024*1024) or not safe_file(manifest,65536)
            or hashlib.sha256(manifest.read_bytes()).hexdigest()!='220cfc26cab422113d2caf6ce61080e8020a0fb5f9f2548916e828fa3cad43be'):fail('nonbg7_fields_source_binding')
    parent=home/'.anytoour-match';root=parent/'operations'
    for folder in (parent,root):
        if not folder.is_dir() or folder.is_symlink() or folder.resolve()!=folder:fail('nonbg7_fields_private_root')
    child=root/operation
    if child.exists() or child.is_symlink():fail('nonbg7_fields_child_exists_no_replay')
    reservation={'operation':operation,'source_sha':source,'batch':'nonbg7-unexported-fields-20261004',
                 'provider_http_calls':0,'maximum_writes':0,'state':'reserved_before_retained_read'}
    def exclusive(path,value):
        raw=json.dumps(value,sort_keys=True,separators=(',',':')).encode()+b'\n'
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as stream:
            if stream.write(raw)!=len(raw):fail('nonbg7_fields_reservation_short_write')
            stream.flush();os.fsync(stream.fileno())
        fd=os.open(path.parent,os.O_RDONLY|os.O_DIRECTORY)
        try:os.fsync(fd)
        finally:os.close(fd)
        if path.read_bytes()!=raw:fail('nonbg7_fields_reservation_readback')
    exclusive(parent/'nonbg7-unexported-fields-batch-20261004.json',reservation)
    child.mkdir(mode=0o700)
    fd=os.open(root,os.O_RDONLY|os.O_DIRECTORY)
    try:os.fsync(fd)
    finally:os.close(fd)
    exclusive(child/'reservation.json',reservation)
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_SOURCE_ROOT':str(stage),
                'MATCH_OPERATION_DIR':str(child),'MATCH_MANIFEST_PATH':str(manifest),'MATCH_SOURCE_SHA':source})
    run=subprocess.run(['python3',str(runner),'--execute'],cwd=project,env=env,capture_output=True,text=True,timeout=300)
    result_path=child/'result.json';receipt_path=child/'receipt.json';input_path=child/'current-input.json'
    if (not safe_file(result_path,2*1024*1024) or not safe_file(receipt_path,65536)
            or not safe_file(input_path,16*1024*1024) or run.stderr.strip()
            or len(run.stdout.encode())>65536):fail('nonbg7_fields_terminal_missing_no_replay')
    import importlib.util
    spec=importlib.util.spec_from_file_location('checked_nonbg7_fields_source',runner)
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
    data=safe_json(result_path,2*1024*1024);receipt=safe_json(receipt_path,65536)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    input_digest=hashlib.sha256(input_path.read_bytes()).hexdigest()
    summary=validate_match_nonbg7_fields(data,receipt,digest,input_digest,source,module.validate_result)
    successful=summary['state'] in ('completed_read_only_nonbg7_fields','completed_read_only_nonbg7_fields_incomplete')
    if run.returncode!=(0 if successful else 2):fail('nonbg7_fields_exit_binding')
    stdout=json.loads(run.stdout)
    if stdout!={k:data[k] for k in ('state','rows_examined','accepted','written')}:fail('nonbg7_fields_stdout_binding')
    return {'result_sha256':digest,'private_input_sha256':input_digest,'successful':successful,'no_replay':True,'summary':summary}

'''

REMOTE_NF7_DISPATCH = r'''    if mode=='match-nonbg7-unexported-fields-readonly':
        lane=run_match_nonbg7_fields(stage)
        result['match_nonbg7_unexported_fields_readonly']=lane
        result['supplier_calls']=0
        result['database_reads']=lane['summary']['database_reads']
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete' if lane['successful'] else 'terminal_nonzero_no_replay'

'''

REMOTE_NU5_HANDLER = r'''
def validate_match_nonbg5_url_paths(data,receipt,digest,input_digest,expected_source,validate_source):
    fixed={'schema':'match-nonbg5-retained-url-paths-readonly-result/1',
           'operation':'int-andromeda-match-nonbg5-retained-url-paths-20261004-v1',
           'batch':'nonbg5-retained-url-paths-20261004','source_sha':expected_source,
           'provider_http_calls':0,'physical_http_attempts':0,'database_writes':0,
           'mapping_writes':0,'booking_calls':0,'lead_calls':0,'accepted':0,'written':0,
           'safe_to_write_now':False,'acceptance_evaluated':False,
           'global_uniqueness_evaluated':False,'no_replay':True,
           'operator_ids':[13,25,43],'requested_rows':5,'requested_sources':4}
    if (not isinstance(data,dict) or not isinstance(receipt,dict)
            or any(type(data.get(k)) is not type(v) or data.get(k)!=v for k,v in fixed.items())
            or type(data.get('database_reads')) is not int or data['database_reads']!=0
            or data.get('private_input_sha256')!=input_digest
            or receipt.get('private_input_sha256')!=input_digest
            or receipt.get('result_sha256')!=digest):fail('nonbg5_url_paths_terminal_binding')
    for k,v in receipt.items():
        if k not in ('result_sha256','private_input_sha256') and (k not in data or type(v) is not type(data[k]) or v!=data[k]):fail('nonbg5_url_paths_receipt_binding')
    if set(receipt)!={'operation','batch','source_sha','state','result_sha256','private_input_sha256',
                     'provider_http_calls','physical_http_attempts','database_reads','database_writes',
                     'mapping_writes','booking_calls','lead_calls','accepted','written',
                     'safe_to_write_now','acceptance_evaluated','global_uniqueness_evaluated','no_replay'}:fail('nonbg5_url_paths_receipt_shape')
    try:validate_source(data)
    except Exception:fail('nonbg5_url_paths_source_validation')
    if data['state'] not in ('completed_read_only_nonbg5_url_paths','completed_read_only_nonbg5_url_paths_incomplete','terminal_failed_no_replay'):fail('nonbg5_url_paths_terminal_state')
    return data

def run_match_nonbg5_url_paths(stage):
    if (operation!='int-andromeda-match-nonbg5-retained-url-paths-20261004-v1'
            or payload.get('batch')!='nonbg5-retained-url-paths-20261004'
            or type(payload.get('maximum_writes')) is not int or payload['maximum_writes']!=0
            or type(payload.get('provider_http_calls')) is not int or payload['provider_http_calls']!=0):fail('nonbg5_url_paths_scope')
    runner=stage/'scripts/diagnostics/hotel_match_nonbg5_retained_url_paths_readonly_v1.py'
    manifest=stage/'scripts/diagnostics/fixtures/hotel_match_nonbg5_retained_url_paths_readonly_v1.json'
    if (not safe_file(runner,2*1024*1024) or not safe_file(manifest,65536)
            or hashlib.sha256(manifest.read_bytes()).hexdigest()!='a24dd81ad112bc220fda3721bfa98985460dc08e74ac6fadc9bb596e188fb269'):fail('nonbg5_url_paths_source_binding')
    parent=home/'.anytoour-match';root=parent/'operations'
    for folder in (parent,root):
        if not folder.is_dir() or folder.is_symlink() or folder.resolve()!=folder:fail('nonbg5_url_paths_private_root')
    child=root/operation
    if child.exists() or child.is_symlink():fail('nonbg5_url_paths_child_exists_no_replay')
    reservation={'operation':operation,'source_sha':source,'batch':'nonbg5-retained-url-paths-20261004',
                 'provider_http_calls':0,'maximum_writes':0,'state':'reserved_before_retained_read'}
    def exclusive(path,value):
        raw=json.dumps(value,sort_keys=True,separators=(',',':')).encode()+b'\n'
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as stream:
            if stream.write(raw)!=len(raw):fail('nonbg5_url_paths_reservation_short_write')
            stream.flush();os.fsync(stream.fileno())
        fd=os.open(path.parent,os.O_RDONLY|os.O_DIRECTORY)
        try:os.fsync(fd)
        finally:os.close(fd)
        if path.read_bytes()!=raw:fail('nonbg5_url_paths_reservation_readback')
    exclusive(parent/'nonbg5-retained-url-paths-batch-20261004.json',reservation)
    child.mkdir(mode=0o700)
    fd=os.open(root,os.O_RDONLY|os.O_DIRECTORY)
    try:os.fsync(fd)
    finally:os.close(fd)
    exclusive(child/'reservation.json',reservation)
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_SOURCE_ROOT':str(stage),
                'MATCH_OPERATION_DIRECTORY':str(child),'MATCH_MANIFEST':str(manifest),'MATCH_SOURCE_SHA':source})
    run=subprocess.run(['python3',str(runner),'--execute'],cwd=project,env=env,capture_output=True,text=True,timeout=300)
    result_path=child/'result.json';receipt_path=child/'receipt.json';input_path=child/'current-input.json'
    if (not safe_file(result_path,2*1024*1024) or not safe_file(receipt_path,65536)
            or not safe_file(input_path,16*1024*1024) or run.stderr.strip()
            or len(run.stdout.encode())>65536):fail('nonbg5_url_paths_terminal_missing_no_replay')
    import importlib.util
    spec=importlib.util.spec_from_file_location('checked_nonbg5_url_paths_source',runner)
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
    data=safe_json(result_path,2*1024*1024);receipt=safe_json(receipt_path,65536)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    input_digest=hashlib.sha256(input_path.read_bytes()).hexdigest()
    summary=validate_match_nonbg5_url_paths(data,receipt,digest,input_digest,source,module.validate_result)
    successful=summary['state'] in ('completed_read_only_nonbg5_url_paths','completed_read_only_nonbg5_url_paths_incomplete')
    if run.returncode!=(0 if successful else 2):fail('nonbg5_url_paths_exit_binding')
    stdout=json.loads(run.stdout)
    if stdout!={k:data[k] for k in ('state','rows_examined','accepted','written')}:fail('nonbg5_url_paths_stdout_binding')
    return {'result_sha256':digest,'private_input_sha256':input_digest,'successful':successful,'no_replay':True,'summary':summary}

'''

REMOTE_NR5_HANDLER = r'''
def validate_match_nonbg5_terminal_readback(data,receipt,digest,input_digest,expected_source,validate_source):
    fixed={'schema':'match-nonbg5-url-paths-terminal-readback-result/1',
           'operation':'int-andromeda-match-nonbg5-url-paths-terminal-readback-20261004-v1',
           'batch':'nonbg5-url-paths-terminal-readback-20261004','source_sha':expected_source,
           'provider_http_calls':0,'physical_http_attempts':0,'database_writes':0,
           'mapping_writes':0,'booking_calls':0,'lead_calls':0,'accepted':0,'written':0,
           'safe_to_write_now':False,'acceptance_evaluated':False,
           'global_uniqueness_evaluated':False,'no_replay':True,
           'requested_records':6}
    if (not isinstance(data,dict) or not isinstance(receipt,dict)
            or any(type(data.get(k)) is not type(v) or data.get(k)!=v for k,v in fixed.items())
            or type(data.get('database_reads')) is not int or data['database_reads']!=0
            or data.get('private_input_sha256')!=input_digest
            or receipt.get('private_input_sha256')!=input_digest
            or receipt.get('result_sha256')!=digest):fail('nonbg5_terminal_readback_terminal_binding')
    for k,v in receipt.items():
        if k not in ('result_sha256','private_input_sha256') and (k not in data or type(v) is not type(data[k]) or v!=data[k]):fail('nonbg5_terminal_readback_receipt_binding')
    if set(receipt)!={'operation','batch','source_sha','state','result_sha256','private_input_sha256',
                     'provider_http_calls','physical_http_attempts','database_reads','database_writes',
                     'mapping_writes','booking_calls','lead_calls','accepted','written',
                     'safe_to_write_now','acceptance_evaluated','global_uniqueness_evaluated','no_replay'}:fail('nonbg5_terminal_readback_receipt_shape')
    try:validate_source(data)
    except Exception:fail('nonbg5_terminal_readback_source_validation')
    if data['state'] not in ('completed_read_only_nonbg5_url_paths_terminal_readback','completed_read_only_nonbg5_url_paths_terminal_readback_incomplete','terminal_failed_no_replay'):fail('nonbg5_terminal_readback_terminal_state')
    return data

def run_match_nonbg5_terminal_readback(stage):
    if (operation!='int-andromeda-match-nonbg5-url-paths-terminal-readback-20261004-v1'
            or payload.get('batch')!='nonbg5-url-paths-terminal-readback-20261004'
            or type(payload.get('maximum_writes')) is not int or payload['maximum_writes']!=0
            or type(payload.get('provider_http_calls')) is not int or payload['provider_http_calls']!=0):fail('nonbg5_terminal_readback_scope')
    runner=stage/'scripts/diagnostics/hotel_match_nonbg5_url_paths_terminal_readback_v1.py'
    manifest=stage/'scripts/diagnostics/fixtures/hotel_match_nonbg5_url_paths_terminal_readback_v1.json'
    if (not safe_file(runner,2*1024*1024) or not safe_file(manifest,65536)
            or hashlib.sha256(manifest.read_bytes()).hexdigest()!='515ccfc283244713f6ecd3b87c3bc5829e1173d9468a66e24d2fa54379950151'):fail('nonbg5_terminal_readback_source_binding')
    old_runner=stage/'scripts/diagnostics/hotel_match_nonbg5_retained_url_paths_readonly_v1.py'
    old_manifest=stage/'scripts/diagnostics/fixtures/hotel_match_nonbg5_retained_url_paths_readonly_v1.json'
    if (not safe_file(old_runner,28459) or not safe_file(old_manifest,42821)
            or hashlib.sha256(old_runner.read_bytes()).hexdigest()!='8437cdb48cc571ff273cfdb95f9e8c5aca9cde5a6586cb93d99f60303961b356'
            or hashlib.sha256(old_manifest.read_bytes()).hexdigest()!='a24dd81ad112bc220fda3721bfa98985460dc08e74ac6fadc9bb596e188fb269'):fail('nonbg5_terminal_readback_validator_binding')
    parent=home/'.anytoour-match';root=parent/'operations'
    for folder in (parent,root):
        if not folder.is_dir() or folder.is_symlink() or folder.resolve()!=folder:fail('nonbg5_terminal_readback_private_root')
    child=root/operation
    if child.exists() or child.is_symlink():fail('nonbg5_terminal_readback_child_exists_no_replay')
    reservation={'operation':operation,'source_sha':source,'batch':'nonbg5-url-paths-terminal-readback-20261004',
                 'provider_http_calls':0,'maximum_writes':0,'state':'reserved_before_retained_read'}
    def exclusive(path,value):
        raw=json.dumps(value,sort_keys=True,separators=(',',':')).encode()+b'\n'
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as stream:
            if stream.write(raw)!=len(raw):fail('nonbg5_terminal_readback_reservation_short_write')
            stream.flush();os.fsync(stream.fileno())
        fd=os.open(path.parent,os.O_RDONLY|os.O_DIRECTORY)
        try:os.fsync(fd)
        finally:os.close(fd)
        if path.read_bytes()!=raw:fail('nonbg5_terminal_readback_reservation_readback')
    exclusive(parent/'nonbg5-url-paths-terminal-readback-batch-20261004.json',reservation)
    child.mkdir(mode=0o700)
    fd=os.open(root,os.O_RDONLY|os.O_DIRECTORY)
    try:os.fsync(fd)
    finally:os.close(fd)
    exclusive(child/'reservation.json',reservation)
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_SOURCE_ROOT':str(stage),
                'MATCH_OPERATION_DIR':str(child),'MATCH_MANIFEST_PATH':str(manifest),'MATCH_SOURCE_SHA':source})
    run=subprocess.run(['python3',str(runner),'--execute'],cwd=project,env=env,capture_output=True,text=True,timeout=300)
    result_path=child/'result.json';receipt_path=child/'receipt.json';input_path=child/'current-input.json'
    if (not safe_file(result_path,2*1024*1024) or not safe_file(receipt_path,65536)
            or not safe_file(input_path,16*1024*1024) or run.stderr.strip()
            or len(run.stdout.encode())>65536):fail('nonbg5_terminal_readback_terminal_missing_no_replay')
    import importlib.util
    spec=importlib.util.spec_from_file_location('checked_nonbg5_terminal_readback_source',runner)
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
    data=safe_json(result_path,2*1024*1024);receipt=safe_json(receipt_path,65536)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    input_digest=hashlib.sha256(input_path.read_bytes()).hexdigest()
    summary=validate_match_nonbg5_terminal_readback(data,receipt,digest,input_digest,source,module.validate_result)
    successful=summary['state'] in ('completed_read_only_nonbg5_url_paths_terminal_readback','completed_read_only_nonbg5_url_paths_terminal_readback_incomplete')
    if run.returncode!=(0 if successful else 2):fail('nonbg5_terminal_readback_exit_binding')
    stdout=json.loads(run.stdout)
    if stdout!={k:data[k] for k in ('state','rows_examined','accepted','written')}:fail('nonbg5_terminal_readback_stdout_binding')
    return {'result_sha256':digest,'private_input_sha256':input_digest,'successful':successful,'no_replay':True,'summary':summary}

'''

REMOTE_NR5_DISPATCH = r'''    if mode=='match-nonbg5-url-paths-terminal-readback':
        lane=run_match_nonbg5_terminal_readback(stage)
        result['match_nonbg5_url_paths_terminal_readback']=lane
        result['supplier_calls']=0
        result['database_reads']=lane['summary']['database_reads']
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete' if lane['successful'] else 'terminal_nonzero_no_replay'

'''

REMOTE_PASSIVE_OCT4_HANDLER = r'''
def validate_match_passive_oct4(data,receipt,digest,input_digest,expected_source,validate_source):
    fixed={'schema':'match-passive-oct4-frontier-result/1',
           'operation':'int-andromeda-match-passive-oct4-frontier-20261005-v1',
           'batch':'passive-oct4-before175942','source_sha':expected_source,
           'provider_http_calls':0,'physical_http_attempts':0,'database_writes':0,
           'mapping_writes':0,'booking_calls':0,'lead_calls':0,'accepted':0,'written':0,
           'safe_to_write_now':False,'acceptance_evaluated':False,
           'global_uniqueness_evaluated':False,'original_event_verified':False,
           'session_identity_verified':False,'route_identity_verified':False,
           'raw_samo_evidence_verified':False,'independent_tv_identity_verified':False,
           'current_registry_verified':False,'no_replay':True}
    if (not isinstance(data,dict) or not isinstance(receipt,dict)
            or any(type(data.get(k)) is not type(v) or data.get(k)!=v for k,v in fixed.items())
            or data.get('private_input_sha256')!=input_digest
            or receipt.get('private_input_sha256')!=input_digest
            or receipt.get('result_sha256')!=digest):fail('passive_oct4_terminal_binding')
    receipt_keys={'operation','batch','source_sha','state','private_input_sha256','result_sha256',
                  'provider_http_calls','physical_http_attempts','database_writes','mapping_writes',
                  'booking_calls','lead_calls','accepted','written','database_reads',
                  'database_read_attempts','read_transaction_rolled_back','php_invocations',
                  'safe_to_write_now','acceptance_evaluated','global_uniqueness_evaluated',
                  'original_event_verified','session_identity_verified','route_identity_verified',
                  'raw_samo_evidence_verified','independent_tv_identity_verified',
                  'current_registry_verified','no_replay'}
    if set(receipt)!=receipt_keys:fail('passive_oct4_receipt_shape')
    for key,value in receipt.items():
        if key not in ('result_sha256','private_input_sha256') and (key not in data or type(value) is not type(data[key]) or value!=data[key]):fail('passive_oct4_receipt_binding')
    try:validate_source(data)
    except Exception:fail('passive_oct4_source_validation')
    if data['state'] not in ('completed_read_only','completed_with_holds','held_overflow_no_replay','terminal_failed_no_replay'):fail('passive_oct4_terminal_state')
    return data

def run_match_passive_oct4(stage):
    if (operation!='int-andromeda-match-passive-oct4-frontier-20261005-v1'
            or payload.get('batch')!='passive-oct4-before175942'
            or type(payload.get('maximum_writes')) is not int or payload['maximum_writes']!=0
            or type(payload.get('provider_http_calls')) is not int or payload['provider_http_calls']!=0):fail('passive_oct4_scope')
    runner=stage/'scripts/diagnostics/hotel_match_passive_oct4_frontier_readonly_v1.py'
    manifest=stage/'scripts/diagnostics/fixtures/hotel_match_passive_oct4_frontier_readonly_v1.json'
    source_test=stage/'tests/hotel_match_passive_oct4_frontier_readonly_v1_test.py'
    checked=[(runner,'c86a8b34ef288303f19d93599ff842d7e8b99299231b3dbeb37e364f618af4b3',2*1024*1024),
             (manifest,'64d4b3e497988068676d29b8c6318f6b02ef042f109cef3dda118cfcf164b3d2',65536),
             (source_test,'2b4fabfe3472cb58ad2bf21db5faabd023dd2c2f9b7a3c4bbbf148cf7347486e',2*1024*1024)]
    for path,digest,maximum in checked:
        if path.resolve()!=path or not safe_file(path,maximum) or hashlib.sha256(path.read_bytes()).hexdigest()!=digest:fail('passive_oct4_source_binding')
    parent=home/'.anytoour-match';root=parent/'operations'
    for folder in (parent,root):
        if not folder.is_dir() or folder.is_symlink() or folder.resolve()!=folder:fail('passive_oct4_private_root')
    child=root/operation
    if child.exists() or child.is_symlink():fail('passive_oct4_child_exists_no_replay')
    reservation={'operation':operation,'source_sha':source,'batch':'passive-oct4-before175942',
                 'provider_http_calls':0,'maximum_writes':0,'state':'reserved_before_database_read'}
    def exclusive(path,value):
        raw=json.dumps(value,sort_keys=True,separators=(',',':')).encode()+b'\n'
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
        with os.fdopen(fd,'wb') as stream:
            if stream.write(raw)!=len(raw):fail('passive_oct4_reservation_short_write')
            stream.flush();os.fsync(stream.fileno())
        fd=os.open(path.parent,os.O_RDONLY|os.O_DIRECTORY)
        try:os.fsync(fd)
        finally:os.close(fd)
        if path.read_bytes()!=raw:fail('passive_oct4_reservation_readback')
    exclusive(parent/'passive-oct4-before175942-batch.json',reservation)
    child.mkdir(mode=0o700)
    fd=os.open(root,os.O_RDONLY|os.O_DIRECTORY)
    try:os.fsync(fd)
    finally:os.close(fd)
    exclusive(child/'reservation.json',reservation)
    result_path=child/'result.json';receipt_path=child/'receipt.json';input_path=child/'current-input.json'
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_SOURCE_ROOT':str(stage),
                'MATCH_PRIVATE_DIRECTORY':str(child),'MATCH_CURRENT_MANIFEST_PATH':str(manifest),
                'MATCH_RESULT_PATH':str(result_path),'MATCH_SOURCE_SHA':source})
    run=subprocess.run(['python3',str(runner),'--execute'],cwd=project,env=env,capture_output=True,text=True,timeout=300)
    if (not safe_file(result_path,32*1024*1024) or not safe_file(receipt_path,65536)
            or not safe_file(input_path,136*1024*1024) or run.stderr.strip()
            or len(run.stdout.encode())>65536):fail('passive_oct4_terminal_missing_no_replay')
    import importlib.util
    spec=importlib.util.spec_from_file_location('checked_passive_oct4_source',runner)
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
    try:
        result_raw=module.file_bytes(result_path,32*1024*1024)
        receipt_raw=module.file_bytes(receipt_path,65536)
        input_raw=module.file_bytes(input_path,136*1024*1024)
        data=module.parsed(result_raw);receipt=module.parsed(receipt_raw)
    except Exception:fail('passive_oct4_terminal_artifact_parse')
    digest=hashlib.sha256(result_raw).hexdigest();input_digest=hashlib.sha256(input_raw).hexdigest()
    summary=validate_match_passive_oct4(data,receipt,digest,input_digest,source,
                                      lambda value:module.validate_result(value,receipt,source))
    successful=summary['state'] in ('completed_read_only','completed_with_holds')
    if run.returncode!=(0 if successful else 2):fail('passive_oct4_exit_binding')
    try:stdout=module.parsed(run.stdout)
    except Exception:fail('passive_oct4_stdout_binding')
    stdout_types={'state':str,'rows_examined':int,'accepted':int,'written':int}
    if (not isinstance(stdout,dict) or set(stdout)!=set(stdout_types)
            or any(type(stdout[key]) is not kind or stdout[key]!=data[key] for key,kind in stdout_types.items())):fail('passive_oct4_stdout_binding')
    return {'result_sha256':digest,'private_input_sha256':input_digest,'successful':successful,'no_replay':True,'summary':summary}

'''

REMOTE_PASSIVE_OCT4_DISPATCH = r'''    if mode=='match-passive-oct4-frontier':
        lane=run_match_passive_oct4(stage)
        result['match_passive_oct4_frontier']=lane
        result['supplier_calls']=0
        for key in ('database_reads','database_read_attempts','read_transaction_rolled_back','php_invocations'):
            result[key]=lane['summary'][key]
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete' if lane['successful'] else 'terminal_nonzero_no_replay'

'''

REMOTE_OBSERVED_PAGE1_HANDLER = r'''
def validate_match_observed_page1_identity(data,receipt,digest,input_digest,expected_source,validate_source):
    fixed={'schema':'match-observed-page1-identity-result/1',
           'operation':'int-andromeda-match-observed-page1-identity-20261005-v1',
           'batch':'observed-page1-20261004-175945','source_sha':expected_source,
           'provider_http_calls':0,'physical_http_attempts':0,'database_reads':0,
           'database_writes':0,'mapping_writes':0,'booking_calls':0,'lead_calls':0,
           'accepted':0,'written':0,'safe_to_write_now':False,
           'acceptance_evaluated':False,'global_uniqueness_evaluated':False,
           'raw_samo_evidence_verified':False,'session_identity_verified':False,
           'route_identity_verified':False,'current_registry_verified':False,'no_replay':True}
    if (not isinstance(data,dict) or not isinstance(receipt,dict)
            or any(type(data.get(k)) is not type(v) or data.get(k)!=v for k,v in fixed.items())
            or data.get('private_input_sha256')!=input_digest
            or receipt.get('private_input_sha256')!=input_digest
            or receipt.get('result_sha256')!=digest):fail('observed_page1_terminal_binding')
    receipt_keys={'operation','batch','source_sha','state','result_sha256','private_input_sha256',
                  'provider_http_calls','physical_http_attempts','database_reads','database_writes',
                  'mapping_writes','booking_calls','lead_calls','accepted','written',
                  'safe_to_write_now','acceptance_evaluated','global_uniqueness_evaluated',
                  'raw_samo_evidence_verified','session_identity_verified','route_identity_verified',
                  'current_registry_verified','no_replay'}
    if set(receipt)!=receipt_keys:fail('observed_page1_receipt_shape')
    for k,v in receipt.items():
        if k not in ('result_sha256','private_input_sha256') and (k not in data or type(v) is not type(data[k]) or v!=data[k]):fail('observed_page1_receipt_binding')
    try:validate_source(data)
    except Exception:fail('observed_page1_source_validation')
    if data['state'] not in ('completed_read_only','terminal_failed_no_replay'):fail('observed_page1_terminal_state')
    return data

def run_match_observed_page1_identity(stage):
    if (operation!='int-andromeda-match-observed-page1-identity-20261005-v1'
            or payload.get('batch')!='observed-page1-20261004-175945'
            or type(payload.get('maximum_writes')) is not int or payload['maximum_writes']!=0
            or type(payload.get('provider_http_calls')) is not int or payload['provider_http_calls']!=0):fail('observed_page1_scope')
    runner=stage/'scripts/diagnostics/hotel_match_observed_page1_identity_readonly_v1.py'
    manifest=stage/'scripts/diagnostics/fixtures/hotel_match_observed_page1_identity_readonly_v1.json'
    source_test=stage/'tests/hotel_match_observed_page1_identity_readonly_v1_test.py'
    checked=[(runner,'5c8bc6632295afa46a9faa23c7646a14659148b090f6ba8585d6803be5e978d0',2*1024*1024),
             (manifest,'771fba36e04ad0c051158ac0c08e8e228eef7e1e9e2aa2850d719e3d915dab94',65536),
             (source_test,'c01966ae238c8da1b71a85ba53bd9bc002af28d675426693eef29fda66435163',2*1024*1024)]
    for path,digest,maximum in checked:
        if path.resolve()!=path or not safe_file(path,maximum) or hashlib.sha256(path.read_bytes()).hexdigest()!=digest:fail('observed_page1_source_binding')
    parent=home/'.anytoour-match';root=parent/'operations'
    for folder in (parent,root):
        if not folder.is_dir() or folder.is_symlink() or folder.resolve()!=folder:fail('observed_page1_private_root')
    child=root/operation
    if child.exists() or child.is_symlink():fail('observed_page1_child_exists_no_replay')
    reservation={'operation':operation,'source_sha':source,'batch':'observed-page1-20261004-175945',
                 'provider_http_calls':0,'maximum_writes':0,'state':'reserved_before_retained_read'}
    def exclusive(path,value):
        raw=json.dumps(value,sort_keys=True,separators=(',',':')).encode()+b'\n'
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as stream:
            if stream.write(raw)!=len(raw):fail('observed_page1_reservation_short_write')
            stream.flush();os.fsync(stream.fileno())
        fd=os.open(path.parent,os.O_RDONLY|os.O_DIRECTORY)
        try:os.fsync(fd)
        finally:os.close(fd)
        if path.read_bytes()!=raw:fail('observed_page1_reservation_readback')
    exclusive(parent/'observed-page1-batch-20261004-175945.json',reservation)
    child.mkdir(mode=0o700)
    fd=os.open(root,os.O_RDONLY|os.O_DIRECTORY)
    try:os.fsync(fd)
    finally:os.close(fd)
    exclusive(child/'reservation.json',reservation)
    result_path=child/'result.json';receipt_path=child/'receipt.json';input_path=child/'current-input.json'
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_SOURCE_ROOT':str(stage),
                'MATCH_PRIVATE_DIRECTORY':str(child),'MATCH_CURRENT_MANIFEST_PATH':str(manifest),
                'MATCH_RESULT_PATH':str(result_path),'MATCH_SOURCE_SHA':source})
    run=subprocess.run(['python3',str(runner),'--execute'],cwd=project,env=env,capture_output=True,text=True,timeout=300)
    if (not safe_file(result_path,2*1024*1024) or not safe_file(receipt_path,65536)
            or not safe_file(input_path,16*1024*1024) or run.stderr.strip()
            or len(run.stdout.encode())>65536):fail('observed_page1_terminal_missing_no_replay')
    import importlib.util
    spec=importlib.util.spec_from_file_location('checked_observed_page1_identity_source',runner)
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
    try:
        result_raw=module.file_bytes(result_path,2*1024*1024)
        receipt_raw=module.file_bytes(receipt_path,65536)
        input_raw=module.file_bytes(input_path,16*1024*1024)
        data=module.parsed(result_raw);receipt=module.parsed(receipt_raw)
    except Exception:fail('observed_page1_terminal_artifact_parse')
    digest=hashlib.sha256(result_raw).hexdigest()
    input_digest=hashlib.sha256(input_raw).hexdigest()
    summary=validate_match_observed_page1_identity(data,receipt,digest,input_digest,source,
                                                  lambda value:module.validate_result(value,receipt,source))
    successful=summary['state']=='completed_read_only'
    if run.returncode!=(0 if successful else 2):fail('observed_page1_exit_binding')
    def stdout_pairs(pairs):
        value={}
        for key,item in pairs:
            if key in value:fail('observed_page1_stdout_duplicate_key')
            value[key]=item
        return value
    stdout=json.loads(run.stdout,object_pairs_hook=stdout_pairs)
    stdout_types={'state':str,'examined_offers':int,'accepted':int,'written':int}
    if (not isinstance(stdout,dict) or set(stdout)!=set(stdout_types)
            or any(type(stdout[k]) is not kind or stdout[k]!=data[k] for k,kind in stdout_types.items())):fail('observed_page1_stdout_binding')
    return {'result_sha256':digest,'private_input_sha256':input_digest,'successful':successful,'no_replay':True,'summary':summary}

'''

REMOTE_OBSERVED_PAGE1_DISPATCH = r'''    if mode=='match-observed-page1-identity':
        lane=run_match_observed_page1_identity(stage)
        result['match_observed_page1_identity']=lane
        result['supplier_calls']=0
        result['database_reads']=0
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete' if lane['successful'] else 'terminal_nonzero_no_replay'

'''

REMOTE_NU5_DISPATCH = r'''    if mode=='match-nonbg5-retained-url-paths-readonly':
        lane=run_match_nonbg5_url_paths(stage)
        result['match_nonbg5_retained_url_paths_readonly']=lane
        result['supplier_calls']=0
        result['database_reads']=lane['summary']['database_reads']
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete' if lane['successful'] else 'terminal_nonzero_no_replay'

'''

REMOTE_BF5_HANDLER = r'''
def validate_match_bg5_fields(data,receipt,digest,input_digest,expected_source,validate_source):
    fixed={'schema':'match-bg5-unexported-fields-readonly-result/1',
           'operation':'int-andromeda-match-bg5-unexported-fields-20261004-v1',
           'batch':'bg5-unexported-fields-20261004','source_sha':expected_source,
           'provider_http_calls':0,'physical_http_attempts':0,'database_writes':0,
           'mapping_writes':0,'booking_calls':0,'lead_calls':0,'accepted':0,'written':0,
           'safe_to_write_now':False,'acceptance_evaluated':False,
           'global_uniqueness_evaluated':False,'no_replay':True,
           'operator_ids':[18],'requested_rows':5}
    if (not isinstance(data,dict) or not isinstance(receipt,dict)
            or any(type(data.get(k)) is not type(v) or data.get(k)!=v for k,v in fixed.items())
            or type(data.get('database_reads')) is not int or data['database_reads']!=0
            or data.get('private_input_sha256')!=input_digest
            or receipt.get('private_input_sha256')!=input_digest
            or receipt.get('result_sha256')!=digest):fail('bg5_fields_terminal_binding')
    for k,v in receipt.items():
        if k not in ('result_sha256','private_input_sha256') and (k not in data or type(v) is not type(data[k]) or v!=data[k]):fail('bg5_fields_receipt_binding')
    if set(receipt)!={'operation','batch','source_sha','state','result_sha256','private_input_sha256',
                     'provider_http_calls','physical_http_attempts','database_reads','database_writes',
                     'mapping_writes','booking_calls','lead_calls','accepted','written',
                     'safe_to_write_now','acceptance_evaluated','global_uniqueness_evaluated','no_replay'}:fail('bg5_fields_receipt_shape')
    try:validate_source(data)
    except Exception:fail('bg5_fields_source_validation')
    if data['state'] not in ('completed_read_only_bg5_fields','completed_read_only_bg5_fields_incomplete','terminal_failed_no_replay'):fail('bg5_fields_terminal_state')
    return data

def run_match_bg5_fields(stage):
    if (operation!='int-andromeda-match-bg5-unexported-fields-20261004-v1'
            or payload.get('batch')!='bg5-unexported-fields-20261004'
            or type(payload.get('maximum_writes')) is not int or payload['maximum_writes']!=0
            or type(payload.get('provider_http_calls')) is not int or payload['provider_http_calls']!=0):fail('bg5_fields_scope')
    runner=stage/'scripts/diagnostics/hotel_match_bg5_unexported_fields_readonly_v1.py'
    manifest=stage/'scripts/diagnostics/fixtures/hotel_match_bg5_unexported_fields_readonly_v1.json'
    if (not safe_file(runner,2*1024*1024) or not safe_file(manifest,65536)
            or hashlib.sha256(manifest.read_bytes()).hexdigest()!='715a8e88de3cbc1662e8319dce3cb13cd75dce1a11b8c2f199049ddaba676d5c'):fail('bg5_fields_source_binding')
    parent=home/'.anytoour-match';root=parent/'operations'
    for folder in (parent,root):
        if not folder.is_dir() or folder.is_symlink() or folder.resolve()!=folder:fail('bg5_fields_private_root')
    child=root/operation
    if child.exists() or child.is_symlink():fail('bg5_fields_child_exists_no_replay')
    reservation={'operation':operation,'source_sha':source,'batch':'bg5-unexported-fields-20261004',
                 'provider_http_calls':0,'maximum_writes':0,'state':'reserved_before_retained_read'}
    def exclusive(path,value):
        raw=json.dumps(value,sort_keys=True,separators=(',',':')).encode()+b'\n'
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as stream:
            if stream.write(raw)!=len(raw):fail('bg5_fields_reservation_short_write')
            stream.flush();os.fsync(stream.fileno())
        fd=os.open(path.parent,os.O_RDONLY|os.O_DIRECTORY)
        try:os.fsync(fd)
        finally:os.close(fd)
        if path.read_bytes()!=raw:fail('bg5_fields_reservation_readback')
    exclusive(parent/'bg5-unexported-fields-batch-20261004.json',reservation)
    child.mkdir(mode=0o700)
    fd=os.open(root,os.O_RDONLY|os.O_DIRECTORY)
    try:os.fsync(fd)
    finally:os.close(fd)
    exclusive(child/'reservation.json',reservation)
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_SOURCE_ROOT':str(stage),
                'MATCH_OPERATION_DIR':str(child),'MATCH_MANIFEST_PATH':str(manifest),'MATCH_SOURCE_SHA':source})
    run=subprocess.run(['python3',str(runner),'--execute'],cwd=project,env=env,capture_output=True,text=True,timeout=300)
    result_path=child/'result.json';receipt_path=child/'receipt.json';input_path=child/'current-input.json'
    if (not safe_file(result_path,2*1024*1024) or not safe_file(receipt_path,65536)
            or not safe_file(input_path,2*1024*1024) or run.stderr.strip()
            or len(run.stdout.encode())>65536):fail('bg5_fields_terminal_missing_no_replay')
    import importlib.util
    spec=importlib.util.spec_from_file_location('checked_bg5_fields_source',runner)
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
    data=safe_json(result_path,2*1024*1024);receipt=safe_json(receipt_path,65536)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    input_digest=hashlib.sha256(input_path.read_bytes()).hexdigest()
    summary=validate_match_bg5_fields(data,receipt,digest,input_digest,source,module.validate_result)
    successful=summary['state'] in ('completed_read_only_bg5_fields','completed_read_only_bg5_fields_incomplete')
    if run.returncode!=(0 if successful else 2):fail('bg5_fields_exit_binding')
    stdout=json.loads(run.stdout)
    if stdout!={k:data[k] for k in ('state','rows_examined','accepted','written')}:fail('bg5_fields_stdout_binding')
    return {'result_sha256':digest,'private_input_sha256':input_digest,'successful':successful,'no_replay':True,'summary':summary}

'''

REMOTE_BF5_DISPATCH = r'''    if mode=='match-bg5-unexported-fields-readonly':
        lane=run_match_bg5_fields(stage)
        result['match_bg5_unexported_fields_readonly']=lane
        result['supplier_calls']=0
        result['database_reads']=lane['summary']['database_reads']
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete' if lane['successful'] else 'terminal_nonzero_no_replay'

'''

REMOTE_BF8_HANDLER = r'''
def validate_match_bg8_fields(data,receipt,digest,input_digest,expected_source,validate_source):
    fixed={'schema':'match-bg8-unexported-fields-readonly-result/1',
           'operation':'int-andromeda-match-bg8-unexported-fields-20261004-v1',
           'batch':'bg8-unexported-fields-20261004','source_sha':expected_source,
           'provider_http_calls':0,'physical_http_attempts':0,'database_writes':0,
           'mapping_writes':0,'booking_calls':0,'lead_calls':0,'accepted':0,'written':0,
           'safe_to_write_now':False,'acceptance_evaluated':False,
           'global_uniqueness_evaluated':False,'no_replay':True,
           'operator_ids':[18],'requested_rows':8}
    if (not isinstance(data,dict) or not isinstance(receipt,dict)
            or any(type(data.get(k)) is not type(v) or data.get(k)!=v for k,v in fixed.items())
            or type(data.get('database_reads')) is not int or data['database_reads']!=0
            or data.get('private_input_sha256')!=input_digest
            or receipt.get('private_input_sha256')!=input_digest
            or receipt.get('result_sha256')!=digest):fail('bg8_fields_terminal_binding')
    for k,v in receipt.items():
        if k not in ('result_sha256','private_input_sha256') and (k not in data or type(v) is not type(data[k]) or v!=data[k]):fail('bg8_fields_receipt_binding')
    if set(receipt)!={'operation','batch','source_sha','state','result_sha256','private_input_sha256',
                     'provider_http_calls','physical_http_attempts','database_reads','database_writes',
                     'mapping_writes','booking_calls','lead_calls','accepted','written',
                     'safe_to_write_now','acceptance_evaluated','global_uniqueness_evaluated','no_replay'}:fail('bg8_fields_receipt_shape')
    try:validate_source(data)
    except Exception:fail('bg8_fields_source_validation')
    if data['state'] not in ('completed_read_only_bg8_fields','completed_read_only_bg8_fields_incomplete','terminal_failed_no_replay'):fail('bg8_fields_terminal_state')
    return data

def run_match_bg8_fields(stage):
    if (operation!='int-andromeda-match-bg8-unexported-fields-20261004-v1'
            or payload.get('batch')!='bg8-unexported-fields-20261004'
            or payload.get('maximum_writes')!=0 or payload.get('provider_http_calls')!=0):fail('bg8_fields_scope')
    runner=stage/'scripts/diagnostics/hotel_match_bg8_unexported_fields_readonly_v1.py'
    manifest=stage/'scripts/diagnostics/fixtures/hotel_match_bg8_unexported_fields_readonly_v1.json'
    if (not safe_file(runner,2*1024*1024) or not safe_file(manifest,65536)
            or hashlib.sha256(manifest.read_bytes()).hexdigest()!='8f51faf57a29343f3989c58315b939e10295e93befb5da8d6056763237e21207'):fail('bg8_fields_source_binding')
    parent=home/'.anytoour-match';root=parent/'operations'
    for folder in (parent,root):
        if not folder.is_dir() or folder.is_symlink() or folder.resolve()!=folder:fail('bg8_fields_private_root')
    child=root/operation
    if child.exists() or child.is_symlink():fail('bg8_fields_child_exists_no_replay')
    reservation={'operation':operation,'source_sha':source,'batch':'bg8-unexported-fields-20261004',
                 'provider_http_calls':0,'maximum_writes':0,'state':'reserved_before_retained_read'}
    def exclusive(path,value):
        raw=json.dumps(value,sort_keys=True,separators=(',',':')).encode()+b'\n'
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as stream:
            if stream.write(raw)!=len(raw):fail('bg8_fields_reservation_short_write')
            stream.flush();os.fsync(stream.fileno())
        fd=os.open(path.parent,os.O_RDONLY|os.O_DIRECTORY)
        try:os.fsync(fd)
        finally:os.close(fd)
        if path.read_bytes()!=raw:fail('bg8_fields_reservation_readback')
    exclusive(parent/'bg8-unexported-fields-batch-20261004.json',reservation)
    child.mkdir(mode=0o700)
    fd=os.open(root,os.O_RDONLY|os.O_DIRECTORY)
    try:os.fsync(fd)
    finally:os.close(fd)
    exclusive(child/'reservation.json',reservation)
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_SOURCE_ROOT':str(stage),
                'MATCH_OPERATION_DIR':str(child),'MATCH_MANIFEST_PATH':str(manifest),'MATCH_SOURCE_SHA':source})
    run=subprocess.run(['python3',str(runner),'--execute'],cwd=project,env=env,capture_output=True,text=True,timeout=300)
    result_path=child/'result.json';receipt_path=child/'receipt.json';input_path=child/'current-input.json'
    if (not safe_file(result_path,32*1024*1024) or not safe_file(receipt_path,65536)
            or not safe_file(input_path,64*1024*1024) or run.stderr.strip()
            or len(run.stdout.encode())>65536):fail('bg8_fields_terminal_missing_no_replay')
    import importlib.util
    spec=importlib.util.spec_from_file_location('checked_bg8_fields_source',runner)
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
    data=safe_json(result_path,32*1024*1024);receipt=safe_json(receipt_path,65536)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    input_digest=hashlib.sha256(input_path.read_bytes()).hexdigest()
    summary=validate_match_bg8_fields(data,receipt,digest,input_digest,source,module.validate_result)
    successful=summary['state'] in ('completed_read_only_bg8_fields','completed_read_only_bg8_fields_incomplete')
    if run.returncode!=(0 if successful else 2):fail('bg8_fields_exit_binding')
    stdout=json.loads(run.stdout)
    if stdout!={k:data[k] for k in ('state','rows_examined','accepted','written')}:fail('bg8_fields_stdout_binding')
    return {'result_sha256':digest,'private_input_sha256':input_digest,'successful':successful,'no_replay':True,'summary':summary}

'''

REMOTE_BF8_DISPATCH = r'''    if mode=='match-bg8-unexported-fields-readonly':
        lane=run_match_bg8_fields(stage)
        result['match_bg8_unexported_fields_readonly']=lane
        result['supplier_calls']=0
        result['database_reads']=lane['summary']['database_reads']
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete' if lane['successful'] else 'terminal_nonzero_no_replay'

'''

REMOTE_DELTA_HANDLER = r'''
def validate_match_user_delta(data,receipt,digest,input_digest,expected_source,validate_source):
    fixed={'schema':'match-user-search-delta-readonly-result/1',
           'operation':'int-andromeda-match-user-search-delta-20261004-v1',
           'batch':'user-search-delta-20261004','source_sha':expected_source,
           'provider_http_calls':0,'physical_http_attempts':0,'database_writes':0,
           'mapping_writes':0,'booking_calls':0,'lead_calls':0,'accepted':0,'written':0,
           'safe_to_write_now':False,'acceptance_evaluated':False,
           'global_uniqueness_evaluated':False,'no_replay':True,
           'operator_ids':[13,18,25,43],
           'window_civil':{'lower_exclusive':'2026-10-02 12:46:00','upper_inclusive':'2026-10-03 09:23:17'}}
    if (not isinstance(data,dict) or not isinstance(receipt,dict)
            or any(type(data.get(k)) is not type(v) or data.get(k)!=v for k,v in fixed.items())
            or type(data.get('database_reads')) is not int or data['database_reads'] not in (0,1)
            or data.get('private_input_sha256')!=input_digest
            or receipt.get('private_input_sha256')!=input_digest
            or receipt.get('result_sha256')!=digest):fail('delta_terminal_binding')
    for k,v in receipt.items():
        if k not in ('result_sha256','private_input_sha256') and (k not in data or type(v) is not type(data[k]) or v!=data[k]):fail('delta_receipt_binding')
    if set(receipt)!={'operation','batch','source_sha','state','result_sha256','private_input_sha256',
                     'provider_http_calls','physical_http_attempts','database_reads','database_writes',
                     'mapping_writes','booking_calls','lead_calls','accepted','written',
                     'safe_to_write_now','acceptance_evaluated','global_uniqueness_evaluated','no_replay'}:fail('delta_receipt_shape')
    try:validate_source(data)
    except Exception:fail('delta_source_validation')
    if data['state'] not in ('completed_read_only_delta','completed_read_only_delta_incomplete','terminal_failed_no_replay'):fail('delta_terminal_state')
    return data

def run_match_user_delta(stage):
    if (operation!='int-andromeda-match-user-search-delta-20261004-v1'
            or payload.get('batch')!='user-search-delta-20261004'
            or payload.get('maximum_writes')!=0 or payload.get('provider_http_calls')!=0):fail('delta_scope')
    runner=stage/'scripts/diagnostics/hotel_match_user_search_delta_readonly_v1.py'
    manifest=stage/'scripts/diagnostics/fixtures/hotel_match_user_search_delta_readonly_v1.json'
    if (not safe_file(runner,2*1024*1024) or not safe_file(manifest,65536)
            or hashlib.sha256(manifest.read_bytes()).hexdigest()!='a58f13ec8513a612e5ae93302c81491ac132079b0f654ae72344385e6edf8057'):fail('delta_source_binding')
    parent=home/'.anytoour-match';root=parent/'operations'
    for folder in (parent,root):
        if not folder.is_dir() or folder.is_symlink() or folder.resolve()!=folder:fail('delta_private_root')
    child=root/operation
    if child.exists() or child.is_symlink():fail('delta_child_exists_no_replay')
    reservation={'operation':operation,'source_sha':source,'batch':'user-search-delta-20261004',
                 'provider_http_calls':0,'maximum_writes':0,'state':'reserved_before_db_read'}
    def exclusive(path,value):
        raw=json.dumps(value,sort_keys=True,separators=(',',':')).encode()+b'\n'
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as stream:
            if stream.write(raw)!=len(raw):fail('delta_reservation_short_write')
            stream.flush();os.fsync(stream.fileno())
        fd=os.open(path.parent,os.O_RDONLY|os.O_DIRECTORY)
        try:os.fsync(fd)
        finally:os.close(fd)
        if path.read_bytes()!=raw:fail('delta_reservation_readback')
    exclusive(parent/'user-search-delta-batch-20261004.json',reservation)
    child.mkdir(mode=0o700);exclusive(child/'reservation.json',reservation)
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_SOURCE_ROOT':str(stage),
                'MATCH_OPERATION_DIR':str(child),'MATCH_MANIFEST_PATH':str(manifest),'MATCH_SOURCE_SHA':source})
    run=subprocess.run(['python3',str(runner),'--execute'],cwd=project,env=env,capture_output=True,text=True,timeout=300)
    result_path=child/'result.json';receipt_path=child/'receipt.json';input_path=child/'current-input.json'
    if (not safe_file(result_path,32*1024*1024) or not safe_file(receipt_path,65536)
            or not safe_file(input_path,64*1024*1024) or run.stderr.strip()
            or len(run.stdout.encode())>65536):fail('delta_terminal_missing_no_replay')
    import importlib.util
    spec=importlib.util.spec_from_file_location('checked_delta_source',runner)
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
    data=safe_json(result_path,32*1024*1024);receipt=safe_json(receipt_path,65536)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    input_digest=hashlib.sha256(input_path.read_bytes()).hexdigest()
    summary=validate_match_user_delta(data,receipt,digest,input_digest,source,module.validate_result)
    successful=summary['state'] in ('completed_read_only_delta','completed_read_only_delta_incomplete')
    if run.returncode!=(0 if successful else 2):fail('delta_exit_binding')
    stdout=json.loads(run.stdout)
    if stdout!={k:data[k] for k in ('state','observations_selected','accepted','written')}:fail('delta_stdout_binding')
    return {'result_sha256':digest,'private_input_sha256':input_digest,'successful':successful,'no_replay':True,'summary':summary}

'''

REMOTE_DELTA_DISPATCH = r'''    if mode=='match-user-search-delta-readonly':
        lane=run_match_user_delta(stage)
        result['match_user_search_delta_readonly']=lane
        result['supplier_calls']=0
        result['database_reads']=lane['summary']['database_reads']
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete' if lane['successful'] else 'terminal_nonzero_no_replay'

'''

REMOTE_ANEX2_HANDLER = r'''
def validate_match_anex2(data,receipt,digest,expected_source):
    expected={'2000109038':('43661',109380,4),'2000029745':('44562',159,1)}
    fixed={'schema':'match-anex2-selectors-readonly-result/1',
           'operation':'int-tourvisor-match-anex2-selectors-readonly-20261001-v1',
           'batch':'anex2-mass83-20261001','source_sha':expected_source,
           'requested_rows':2,'tourvisor_account':'TOURVISOR_ANEX_JWT','operator_ids':[13],
           'continue_calls':0,'dates_calls':0,'database_writes':0,'mapping_writes':0,
           'safe_to_write_now':False,'prior_identity_tokens':{'159':['804','44562'],'109380':[]}}
    extra={'state','reason','captured_at_utc','groups','preflight_snapshots','provider_http_calls',
           'physical_http_attempts','database_reads','call_counts','returned_edges',
           'edge_state_counts','no_replay'}
    receipt_fields={'operation','batch','source_sha','state','result_sha256','provider_http_calls',
                    'database_reads','database_writes','mapping_writes','safe_to_write_now','no_replay'}
    if (not isinstance(data,dict) or set(data)!=set(fixed)|extra
            or not isinstance(receipt,dict) or set(receipt)!=receipt_fields
            or any(data.get(k)!=v for k,v in fixed.items()) or receipt.get('result_sha256')!=digest
            or any(receipt.get(k)!=data.get(k) for k in receipt_fields-{'result_sha256'})):
        fail('anex2_terminal_binding')
    ints=('provider_http_calls','physical_http_attempts','database_reads','returned_edges')
    if (any(type(data.get(k)) is not int for k in ints)
            or not 0<=data['provider_http_calls']<=12
            or data['physical_http_attempts']!=data['provider_http_calls']
            or not 0<=data['database_reads']<=14 or not 0<=data['returned_edges']<=2
            or type(data['no_replay']) is not bool or data['no_replay']!=(data['provider_http_calls']>0)):
        fail('anex2_counter_authority')
    for k in ('provider_http_calls','database_reads','database_writes','mapping_writes'):
        if type(receipt.get(k)) is not int or receipt[k]!=data[k]: fail('anex2_receipt_counter')
    if receipt['safe_to_write_now'] is not False or type(receipt['no_replay']) is not bool:
        fail('anex2_receipt_authority')
    state=data['state'];success=state=='completed_read_only'
    if state not in ('completed_read_only','failed_before_provider_access','terminal_failed_no_replay'):
        fail('anex2_terminal_state')
    if ((state=='failed_before_provider_access' and data['provider_http_calls']!=0)
            or (state=='terminal_failed_no_replay' and data['provider_http_calls']==0)
            or (success and data['reason'] is not None)):
        fail('anex2_terminal_counts')
    reason=data['reason']
    if not success and (not isinstance(reason,str) or len(reason)<1 or len(reason)>180
            or re.search(r'[\x00-\x1f\x7f]',reason)): fail('anex2_reason_shape')
    stamp=data['captured_at_utc']
    if not isinstance(stamp,str): fail('anex2_timestamp')
    import datetime as dt
    try:
        parsed=dt.datetime.fromisoformat(stamp.replace('Z','+00:00'))
        if parsed.utcoffset()!=dt.timedelta(0): fail('anex2_timestamp')
    except (ValueError,TypeError): fail('anex2_timestamp')
    calls=data['call_counts'];allowed_calls={'search_start','search_status','search_results','tour_detail'}
    if (not isinstance(calls,dict) or any(k not in allowed_calls or type(v) is not int or v<0 for k,v in calls.items())
            or sum(calls.values())!=data['provider_http_calls']
            or calls.get('search_start',0)>2 or calls.get('search_status',0)>6
            or calls.get('search_results',0)>2 or calls.get('tour_detail',0)>2):
        fail('anex2_call_partition')
    holds={'current_source_not_pending_null','current_source_history_review','target_missing_or_inactive',
           'target_country_changed','target_manual','target_excluded','target_occupied','protected_source'}
    snapshots=data['preflight_snapshots']
    if not isinstance(snapshots,list) or len(snapshots)!=data['database_reads']: fail('anex2_preflight_count')
    last=0
    for snap in snapshots:
        if (not isinstance(snap,dict) or set(snap)!={'sequence','next_http_call','action','rows'}
                or type(snap['sequence']) is not int or snap['sequence']!=last+1
                or type(snap['next_http_call']) is not int or not 1<=snap['next_http_call']<=12
                or not isinstance(snap['action'],str) or not isinstance(snap['rows'],list)
                or not 1<=len(snap['rows'])<=2): fail('anex2_preflight_shape')
        last=snap['sequence'];seen=set()
        for row in snap['rows']:
            if (not isinstance(row,dict) or set(row)!={'source_catalog_id','target_tv_hotel_id','state','holds','safe_to_write_now'}
                    or row['source_catalog_id'] not in expected or row['safe_to_write_now'] is not False):
                fail('anex2_preflight_row')
            native,target,country=expected[row['source_catalog_id']]
            if type(row['target_tv_hotel_id']) is not int or row['target_tv_hotel_id']!=target:
                fail('anex2_preflight_identity')
            hs=row['holds']
            if (not isinstance(hs,list) or hs!=sorted(set(hs)) or any(h not in holds for h in hs)
                    or row['state']!=('eligible' if not hs else 'hold') or target in seen):
                fail('anex2_preflight_state')
            seen.add(target)
    groups=data['groups']
    if not isinstance(groups,list) or len(groups)>2 or (success and len(groups)!=2): fail('anex2_groups')
    edge_count=0;edge_states={}
    edge_base={'source_catalog_id','source_native_id','target_tv_hotel_id','operator_id','namespace',
               'operator_tour_count','tour_id_sha256','state','safe_to_write_now','prior_identity_tokens'}
    edge_optional={'tour_detail_http','operator_link_sha256','operator_link_host','positive_native_candidates',
                   'query_keys','raw_identity_values','raw_identity_tokens','link_state','matches_source_native'}
    group_base={'group','country_id','state','sent','edges'}
    group_optional={'initial_preflight','search_complete','returned_targets'}
    allowed_group_states={'preflight_hold','completed_read_only','current_rows_hold','current_rows_changed'}
    allowed_edge_states={'operator_tour_returned','detail_404','detail_identity_mismatch',
                         'detail_identity_verified','current_hold_before_detail'}
    allowed_links={'missing','invalid','invalid_origin','unexpected_anex_host','secret_bearing_link',
                   'captured_single_native','captured_ambiguous_native','missing_native','not_read'}
    seen_targets=set()
    seen_groups=set()
    for group in groups:
        if (not isinstance(group,dict) or not group_base.issubset(group)
                or set(group)-group_base-group_optional or type(group['group']) is not int
                or group['group'] not in (1,2) or group['group'] in seen_groups
                or group['country_id']!={1:4,2:1}[group['group']]
                or group['state'] not in allowed_group_states or type(group['sent']) is not int
                or not 0<=group['sent']<=1 or not isinstance(group['edges'],list)): fail('anex2_group_shape')
        seen_groups.add(group['group'])
        for edge in group['edges']:
            if (not isinstance(edge,dict) or not edge_base.issubset(edge)
                    or set(edge)-edge_base-edge_optional or edge['source_catalog_id'] not in expected
                    or edge['source_native_id']!=expected[edge['source_catalog_id']][0]
                    or group['country_id']!=expected[edge['source_catalog_id']][2]
                    or edge['prior_identity_tokens']!=(['804','44562'] if edge['target_tv_hotel_id']==159 else [])
                    or type(edge['target_tv_hotel_id']) is not int
                    or edge['target_tv_hotel_id']!=expected[edge['source_catalog_id']][1]
                    or edge['operator_id']!=13 or edge['namespace']!='operator_5'
                    or type(edge['operator_tour_count']) is not int or edge['operator_tour_count']<1
                    or not isinstance(edge['tour_id_sha256'],str) or not re.fullmatch(r'[0-9a-f]{64}',edge['tour_id_sha256'])
                    or edge['state'] not in allowed_edge_states or edge['safe_to_write_now'] is not False
                    or edge['target_tv_hotel_id'] in seen_targets): fail('anex2_edge_identity')
            seen_targets.add(edge['target_tv_hotel_id']);edge_count+=1
            edge_states[edge['state']]=edge_states.get(edge['state'],0)+1
            if 'positive_native_candidates' in edge:
                ids=edge['positive_native_candidates']
                if (not isinstance(ids,list) or ids!=sorted(set(ids))
                        or any(type(v) is not int or v<1 for v in ids)): fail('anex2_native_candidates')
            raw_values=edge.get('raw_identity_values');raw_tokens=edge.get('raw_identity_tokens')
            if (raw_values is None)!=(raw_tokens is None): fail('anex2_raw_identity_pair')
            if raw_values is not None:
                if (not isinstance(raw_values,list) or not isinstance(raw_tokens,list)
                        or any(not isinstance(v,str) or len(v)>512 for v in raw_values)
                        or any(not isinstance(v,str) or len(v)>512 or re.search(r'[\x00-\x1f\x7f]',v) for v in raw_tokens)
                        or raw_tokens!=[token.strip() for value in raw_values for token in value.split(',')]
                        or sorted(set(int(v) for v in raw_tokens if re.fullmatch(r'[1-9][0-9]{0,19}',v)))!=edge.get('positive_native_candidates',[])):
                    fail('anex2_raw_identity')
            if 'query_keys' in edge:
                keys=edge['query_keys']
                if (not isinstance(keys,list) or len(keys)>40 or keys!=sorted(set(keys))
                        or any(not isinstance(v,str) or not re.fullmatch(r'[a-z0-9_.-]{1,80}',v) for v in keys)):
                    fail('anex2_query_keys')
            if 'link_state' in edge and edge['link_state'] not in allowed_links: fail('anex2_link_state')
            if edge.get('link_state') in ('captured_single_native','captured_ambiguous_native'):
                host=edge.get('operator_link_host')
                if not isinstance(host,str) or not (host=='anextour.ru' or host.endswith('.anextour.ru')):
                    fail('anex2_link_host')
            if 'matches_source_native' in edge:
                ids=edge.get('positive_native_candidates',[]);expected_match=ids==[int(edge['source_native_id'])] and edge.get('link_state')=='captured_single_native'
                if type(edge['matches_source_native']) is not bool or edge['matches_source_native']!=expected_match:
                    fail('anex2_native_match')
    if edge_count!=data['returned_edges'] or data['edge_state_counts']!=edge_states: fail('anex2_edge_partition')
    summary={k:v for k,v in data.items() if k!='reason'}
    summary['reason_sha256']=hashlib.sha256(reason.encode()).hexdigest() if reason else None
    return summary

def run_match_anex2(stage):
    if (operation!='int-tourvisor-match-anex2-selectors-readonly-20261001-v1'
            or payload.get('batch')!='anex2-mass83-20261001'
            or type(payload.get('maximum_writes')) is not int or payload['maximum_writes']!=0
            or type(payload.get('provider_http_calls')) is not int or payload['provider_http_calls']!=12):
        fail('anex2_fixed_scope')
    manifest=stage/'scripts/diagnostics/fixtures/hotel_match_anex2_selectors_readonly_v1.json'
    runner=stage/'scripts/diagnostics/hotel_match_anex2_selectors_readonly_v1.py'
    if (not safe_file(runner,2*1024*1024) or not safe_file(manifest,65536)
            or hashlib.sha256(manifest.read_bytes()).hexdigest()!='7bf2cd6dc73f451593308d3c2e78659a98725f5854c93680fcc6ea3f94b4f0f7'):
        fail('anex2_source_binding')
    for path in ('reports/hotel-match-mass83-proof-minimization-20261001.json',
                 'reports/hotel-match-minimal-nonbg-ledger-reconcile-20261001.json'):
        if not safe_file(stage/path,2*1024*1024): fail('anex2_source_binding')
    parent=home/'.anytoour-match';root=parent/'operations'
    for folder in (parent,root):
        if not folder.is_dir() or folder.is_symlink() or folder.resolve()!=folder: fail('anex2_private_root')
    child=root/operation
    if child.exists() or child.is_symlink(): fail('anex2_child_exists_no_replay')
    reservation={'operation':operation,'source_sha':source,'batch':'anex2-mass83-20261001',
                 'maximum_writes':0,'provider_http_calls':12,'state':'reserved_before_db_and_provider',
                 'reserved_at':int(time.time())}
    def exclusive(path,value):
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as stream:
            stream.write(json.dumps(value,sort_keys=True,separators=(',',':')).encode()+b'\n')
            stream.flush();os.fsync(stream.fileno())
        fd=os.open(path.parent,os.O_RDONLY|os.O_DIRECTORY)
        try: os.fsync(fd)
        finally: os.close(fd)
    exclusive(parent/'anex2-selectors-batch-anex2-mass83-20261001.json',reservation)
    child.mkdir(mode=0o700);exclusive(child/'reservation.json',reservation)
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_SOURCE_ROOT':str(stage),
                'MATCH_OPERATION_DIR':str(child),'MATCH_MANIFEST_PATH':str(manifest),
                'MATCH_SOURCE_SHA':source})
    run=subprocess.run(['python3',str(runner),'--execute'],cwd=project,env=env,
                       capture_output=True,text=True,timeout=300)
    result_path=child/'result.json';receipt_path=child/'receipt.json'
    if (not safe_file(result_path,8*1024*1024) or not safe_file(receipt_path,65536)
            or run.stderr.strip() or len(run.stdout.encode())>65536): fail('anex2_terminal_missing_no_replay')
    data=safe_json(result_path,8*1024*1024);receipt=safe_json(receipt_path,65536)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    summary=validate_match_anex2(data,receipt,digest,source)
    successful=summary['state']=='completed_read_only'
    if run.returncode!=(0 if successful else 2): fail('anex2_exit_binding')
    stdout=json.loads(run.stdout)
    if stdout!={k:data[k] for k in ('state','reason','requested_rows','provider_http_calls','returned_edges','edge_state_counts')}:
        fail('anex2_stdout_binding')
    return {'result_sha256':digest,'successful':successful,'no_replay':True,'summary':summary}
'''

REMOTE_ANEX2_DISPATCH = r'''    if mode=='match-anex2-selectors-readonly':
        lane=run_match_anex2(stage)
        result['match_anex2_selectors_readonly']=lane
        result['supplier_calls']=lane['summary']['provider_http_calls']
        result['database_reads']=lane['summary']['database_reads']
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete' if lane['successful'] else 'terminal_nonzero_no_replay'
'''

REMOTE_FUNSUN2_HANDLER = r'''
def validate_match_funsun2(data,receipt,digest,expected_source):
    expected={'2000037261':('354014',59115,4),'2000068203':('789636',70782,4)}
    fixed={'schema':'match-funsun2-selectors-readonly-result/1',
           'operation':'int-tourvisor-match-funsun2-selectors-readonly-20261001-v1',
           'batch':'funsun2-mass83-20261001','source_sha':expected_source,
           'requested_rows':2,'tourvisor_account':'TOURVISOR_ANEX_JWT','operator_ids':[25],
           'continue_calls':0,'dates_calls':0,'database_writes':0,'mapping_writes':0,
           'safe_to_write_now':False}
    extra={'state','reason','captured_at_utc','groups','preflight_snapshots','provider_http_calls',
           'physical_http_attempts','database_reads','call_counts','returned_edges',
           'edge_state_counts','no_replay'}
    receipt_fields={'operation','batch','source_sha','state','result_sha256','provider_http_calls',
                    'database_reads','database_writes','mapping_writes','safe_to_write_now','no_replay'}
    if (not isinstance(data,dict) or set(data)!=set(fixed)|extra
            or not isinstance(receipt,dict) or set(receipt)!=receipt_fields
            or any(data.get(k)!=v for k,v in fixed.items()) or receipt.get('result_sha256')!=digest
            or any(receipt.get(k)!=data.get(k) for k in receipt_fields-{'result_sha256'})):
        fail('funsun2_terminal_binding')
    ints=('provider_http_calls','physical_http_attempts','database_reads','returned_edges')
    if (any(type(data.get(k)) is not int for k in ints)
            or not 0<=data['provider_http_calls']<=7
            or data['physical_http_attempts']!=data['provider_http_calls']
            or not 0<=data['database_reads']<=8 or not 0<=data['returned_edges']<=2
            or type(data['no_replay']) is not bool or data['no_replay']!=(data['provider_http_calls']>0)):
        fail('funsun2_counter_authority')
    for k in ('provider_http_calls','database_reads','database_writes','mapping_writes'):
        if type(receipt.get(k)) is not int or receipt[k]!=data[k]: fail('funsun2_receipt_counter')
    if receipt['safe_to_write_now'] is not False or type(receipt['no_replay']) is not bool:
        fail('funsun2_receipt_authority')
    state=data['state'];success=state=='completed_read_only'
    if state not in ('completed_read_only','failed_before_provider_access','terminal_failed_no_replay'):
        fail('funsun2_terminal_state')
    if ((state=='failed_before_provider_access' and data['provider_http_calls']!=0)
            or (state=='terminal_failed_no_replay' and data['provider_http_calls']==0)
            or (success and data['reason'] is not None)):
        fail('funsun2_terminal_counts')
    reason=data['reason']
    if not success and (not isinstance(reason,str) or len(reason)<1 or len(reason)>180
            or re.search(r'[\x00-\x1f\x7f]',reason)): fail('funsun2_reason_shape')
    stamp=data['captured_at_utc']
    if not isinstance(stamp,str): fail('funsun2_timestamp')
    import datetime as dt
    try:
        parsed=dt.datetime.fromisoformat(stamp.replace('Z','+00:00'))
        if parsed.utcoffset()!=dt.timedelta(0): fail('funsun2_timestamp')
    except (ValueError,TypeError): fail('funsun2_timestamp')
    calls=data['call_counts'];allowed_calls={'search_start','search_status','search_results','tour_detail'}
    if (not isinstance(calls,dict) or any(k not in allowed_calls or type(v) is not int or v<0 for k,v in calls.items())
            or sum(calls.values())!=data['provider_http_calls']
            or calls.get('search_start',0)>1 or calls.get('search_status',0)>3
            or calls.get('search_results',0)>1 or calls.get('tour_detail',0)>2):
        fail('funsun2_call_partition')
    holds={'current_source_not_pending_null','current_source_history_review','target_missing_or_inactive',
           'target_country_changed','target_manual','target_excluded','target_occupied','protected_source'}
    snapshots=data['preflight_snapshots']
    if not isinstance(snapshots,list) or len(snapshots)!=data['database_reads']: fail('funsun2_preflight_count')
    last=0
    for snap in snapshots:
        if (not isinstance(snap,dict) or set(snap)!={'sequence','next_http_call','action','rows'}
                or type(snap['sequence']) is not int or snap['sequence']!=last+1
                or type(snap['next_http_call']) is not int or not 1<=snap['next_http_call']<=7
                or not isinstance(snap['action'],str) or not isinstance(snap['rows'],list)
                or not 1<=len(snap['rows'])<=2): fail('funsun2_preflight_shape')
        last=snap['sequence'];seen=set()
        for row in snap['rows']:
            if (not isinstance(row,dict) or set(row)!={'source_catalog_id','target_tv_hotel_id','state','holds','safe_to_write_now'}
                    or row['source_catalog_id'] not in expected or row['safe_to_write_now'] is not False):
                fail('funsun2_preflight_row')
            native,target,country=expected[row['source_catalog_id']]
            if type(row['target_tv_hotel_id']) is not int or row['target_tv_hotel_id']!=target:
                fail('funsun2_preflight_identity')
            hs=row['holds']
            if (not isinstance(hs,list) or hs!=sorted(set(hs)) or any(h not in holds for h in hs)
                    or row['state']!=('eligible' if not hs else 'hold') or target in seen):
                fail('funsun2_preflight_state')
            seen.add(target)
    groups=data['groups']
    if not isinstance(groups,list) or len(groups)>1 or (success and len(groups)!=1): fail('funsun2_groups')
    edge_count=0;edge_states={}
    edge_base={'source_catalog_id','source_native_id','target_tv_hotel_id','operator_id','namespace',
               'operator_tour_count','tour_id_sha256','state','safe_to_write_now'}
    edge_optional={'tour_detail_http','operator_link_sha256','operator_link_host','positive_native_candidates',
                   'query_keys','raw_identity_values','raw_identity_tokens','link_state','matches_source_native'}
    group_base={'group','country_id','state','sent','edges'}
    group_optional={'initial_preflight','search_complete','returned_targets'}
    allowed_group_states={'preflight_hold','completed_read_only','current_rows_hold','current_rows_changed'}
    allowed_edge_states={'operator_tour_returned','detail_404','detail_identity_mismatch',
                         'detail_identity_verified','current_hold_before_detail'}
    allowed_links={'missing','invalid','invalid_origin','unexpected_funsun_host','secret_bearing_link',
                   'captured_single_native','captured_ambiguous_native','missing_native','not_read'}
    seen_targets=set()
    for group in groups:
        if (not isinstance(group,dict) or not group_base.issubset(group)
                or set(group)-group_base-group_optional or group['group']!=1 or group['country_id']!=4
                or group['state'] not in allowed_group_states or type(group['sent']) is not int
                or not 0<=group['sent']<=2 or not isinstance(group['edges'],list)): fail('funsun2_group_shape')
        for edge in group['edges']:
            if (not isinstance(edge,dict) or not edge_base.issubset(edge)
                    or set(edge)-edge_base-edge_optional or edge['source_catalog_id'] not in expected
                    or edge['source_native_id']!=expected[edge['source_catalog_id']][0]
                    or type(edge['target_tv_hotel_id']) is not int
                    or edge['target_tv_hotel_id']!=expected[edge['source_catalog_id']][1]
                    or edge['operator_id']!=25 or edge['namespace']!='operator_315'
                    or type(edge['operator_tour_count']) is not int or edge['operator_tour_count']<1
                    or not isinstance(edge['tour_id_sha256'],str) or not re.fullmatch(r'[0-9a-f]{64}',edge['tour_id_sha256'])
                    or edge['state'] not in allowed_edge_states or edge['safe_to_write_now'] is not False
                    or edge['target_tv_hotel_id'] in seen_targets): fail('funsun2_edge_identity')
            seen_targets.add(edge['target_tv_hotel_id']);edge_count+=1
            edge_states[edge['state']]=edge_states.get(edge['state'],0)+1
            if 'positive_native_candidates' in edge:
                ids=edge['positive_native_candidates']
                if (not isinstance(ids,list) or ids!=sorted(set(ids))
                        or any(type(v) is not int or v<1 for v in ids)): fail('funsun2_native_candidates')
            raw_values=edge.get('raw_identity_values');raw_tokens=edge.get('raw_identity_tokens')
            if (raw_values is None)!=(raw_tokens is None): fail('funsun2_raw_identity_pair')
            if raw_values is not None:
                if (not isinstance(raw_values,list) or not isinstance(raw_tokens,list)
                        or any(not isinstance(v,str) or len(v)>512 for v in raw_values)
                        or any(not isinstance(v,str) or not re.fullmatch(r'[1-9][0-9]{0,19}',v) for v in raw_tokens)
                        or sorted(set(int(v) for v in raw_tokens))!=edge.get('positive_native_candidates',[])):
                    fail('funsun2_raw_identity')
            if 'query_keys' in edge:
                keys=edge['query_keys']
                if (not isinstance(keys,list) or len(keys)>40 or keys!=sorted(set(keys))
                        or any(not isinstance(v,str) or not re.fullmatch(r'[a-z0-9_.-]{1,80}',v) for v in keys)):
                    fail('funsun2_query_keys')
            if 'link_state' in edge and edge['link_state'] not in allowed_links: fail('funsun2_link_state')
            if edge.get('link_state') in ('captured_single_native','captured_ambiguous_native'):
                host=edge.get('operator_link_host')
                if not isinstance(host,str) or not (host=='fstravel.com' or host.endswith('.fstravel.com')):
                    fail('funsun2_link_host')
            if 'matches_source_native' in edge:
                ids=edge.get('positive_native_candidates',[]);expected_match=ids==[int(edge['source_native_id'])]
                if type(edge['matches_source_native']) is not bool or edge['matches_source_native']!=expected_match:
                    fail('funsun2_native_match')
    if edge_count!=data['returned_edges'] or data['edge_state_counts']!=edge_states: fail('funsun2_edge_partition')
    summary={k:v for k,v in data.items() if k!='reason'}
    summary['reason_sha256']=hashlib.sha256(reason.encode()).hexdigest() if reason else None
    return summary

def run_match_funsun2(stage):
    if (operation!='int-tourvisor-match-funsun2-selectors-readonly-20261001-v1'
            or payload.get('batch')!='funsun2-mass83-20261001'
            or type(payload.get('maximum_writes')) is not int or payload['maximum_writes']!=0
            or type(payload.get('provider_http_calls')) is not int or payload['provider_http_calls']!=7):
        fail('funsun2_fixed_scope')
    manifest=stage/'scripts/diagnostics/fixtures/hotel_match_funsun2_selectors_readonly_v1.json'
    runner=stage/'scripts/diagnostics/hotel_match_funsun2_selectors_readonly_v1.py'
    if (not safe_file(runner,2*1024*1024) or not safe_file(manifest,65536)
            or hashlib.sha256(manifest.read_bytes()).hexdigest()!='093970c2b59b95dc59f1187cd05dd50a3653d6cdd93ad892e5d5ead1685bebae'):
        fail('funsun2_source_binding')
    for path in ('reports/hotel-match-mass83-proof-minimization-20261001.json',
                 'reports/hotel-match-minimal-nonbg-ledger-reconcile-20261001.json'):
        if not safe_file(stage/path,2*1024*1024): fail('funsun2_source_binding')
    parent=home/'.anytoour-match';root=parent/'operations'
    for folder in (parent,root):
        if not folder.is_dir() or folder.is_symlink() or folder.resolve()!=folder: fail('funsun2_private_root')
    child=root/operation
    if child.exists() or child.is_symlink(): fail('funsun2_child_exists_no_replay')
    reservation={'operation':operation,'source_sha':source,'batch':'funsun2-mass83-20261001',
                 'maximum_writes':0,'provider_http_calls':7,'state':'reserved_before_db_and_provider',
                 'reserved_at':int(time.time())}
    def exclusive(path,value):
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as stream:
            stream.write(json.dumps(value,sort_keys=True,separators=(',',':')).encode()+b'\n')
            stream.flush();os.fsync(stream.fileno())
        fd=os.open(path.parent,os.O_RDONLY|os.O_DIRECTORY)
        try: os.fsync(fd)
        finally: os.close(fd)
    exclusive(parent/'funsun2-selectors-batch-funsun2-mass83-20261001.json',reservation)
    child.mkdir(mode=0o700);exclusive(child/'reservation.json',reservation)
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_SOURCE_ROOT':str(stage),
                'MATCH_OPERATION_DIR':str(child),'MATCH_MANIFEST_PATH':str(manifest),
                'MATCH_SOURCE_SHA':source})
    run=subprocess.run(['python3',str(runner),'--execute'],cwd=project,env=env,
                       capture_output=True,text=True,timeout=300)
    result_path=child/'result.json';receipt_path=child/'receipt.json'
    if (not safe_file(result_path,8*1024*1024) or not safe_file(receipt_path,65536)
            or run.stderr.strip() or len(run.stdout.encode())>65536): fail('funsun2_terminal_missing_no_replay')
    data=safe_json(result_path,8*1024*1024);receipt=safe_json(receipt_path,65536)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    summary=validate_match_funsun2(data,receipt,digest,source)
    successful=summary['state']=='completed_read_only'
    if run.returncode!=(0 if successful else 2): fail('funsun2_exit_binding')
    stdout=json.loads(run.stdout)
    if stdout!={k:data[k] for k in ('state','reason','requested_rows','provider_http_calls','returned_edges','edge_state_counts')}:
        fail('funsun2_stdout_binding')
    return {'result_sha256':digest,'successful':successful,'no_replay':True,'summary':summary}
'''

REMOTE_FUNSUN2_DISPATCH = r'''    if mode=='match-funsun2-selectors-readonly':
        lane=run_match_funsun2(stage)
        result['match_funsun2_selectors_readonly']=lane
        result['supplier_calls']=lane['summary']['provider_http_calls']
        result['database_reads']=lane['summary']['database_reads']
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete' if lane['successful'] else 'terminal_nonzero_no_replay'
'''

REMOTE_SOURCE3_HANDLER = r'''
def validate_match_source3(data,receipt,digest,expected_source):
    expected={'163887':(5,'operator_5',1124,'8319'),
              '2000057636':(342,'operator_342',21679,'24891'),
              '2000073063':(5,'operator_5',60766,None)}
    fixed={'schema':'match-source3-native-current-result/1',
           'operation':'int-andromeda-match-source3-native-current-20261001-v1',
           'source_sha':expected_source,'batch':'source3-native-20261001','requested_sources':3,
           'tourvisor_http_calls':0,'database_writes':0,'mapping_writes':0,
           'safe_to_write_now':False,'acceptance_evaluated':False}
    extra={'state','reason','captured_at_utc','preflight_rows','evidence_rows','responses',
           'provider_http_calls','database_reads','no_replay'}
    receipt_fields={'state','operation','source_sha','batch','result_sha256','provider_http_calls',
                    'tourvisor_http_calls','database_reads','database_writes','mapping_writes',
                    'safe_to_write_now','no_replay'}
    if (not isinstance(data,dict) or set(data)!=set(fixed)|extra
            or not isinstance(receipt,dict) or set(receipt)!=receipt_fields
            or any(data.get(k)!=v for k,v in fixed.items()) or receipt.get('result_sha256')!=digest
            or any(receipt.get(k)!=data.get(k) for k in receipt_fields-{'result_sha256'})):
        fail('source3_terminal_binding')
    for item in (data,receipt):
        if (any(type(item[k]) is not int or item[k]!=0 for k in ('tourvisor_http_calls','database_writes','mapping_writes'))
                or type(item['provider_http_calls']) is not int or not 0<=item['provider_http_calls']<=3
                or type(item['database_reads']) is not int or item['database_reads'] not in (0,1)
                or item['safe_to_write_now'] is not False or type(item['no_replay']) is not bool
                or item['no_replay']!=(item['provider_http_calls']>0)):
            fail('source3_zero_write_authority')
    if type(data['requested_sources']) is not int or data['acceptance_evaluated'] is not False:
        fail('source3_acceptance_authority')
    state=data['state'];success=state=='completed_source3_native_current'
    if state not in ('completed_source3_native_current','failed_before_provider','terminal_failed_no_replay'):
        fail('source3_terminal_state')
    if ((state=='failed_before_provider' and data['provider_http_calls']!=0)
            or (state=='terminal_failed_no_replay' and data['provider_http_calls']==0)
            or (success and (data['database_reads']!=1 or data['reason'] is not None))):
        fail('source3_terminal_state_counts')
    reason=data['reason']
    if not success and (not isinstance(reason,str) or not re.fullmatch(r'[A-Za-z0-9_.:-]{1,140}',reason)):
        fail('source3_reason_shape')
    stamp=data['captured_at_utc']
    if not isinstance(stamp,str) or not re.fullmatch(r'\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|\+00:00)',stamp):
        fail('source3_timestamp')
    import datetime as dt
    try: dt.datetime.fromisoformat(stamp.replace('Z','+00:00'))
    except ValueError: fail('source3_timestamp')
    holds={'current_source_not_unique','current_source_not_pending_null','current_source_revision_differs',
           'current_source_history_review','target_missing_or_inactive','target_country_changed',
           'target_manual_or_exclusion','target_occupied'}
    base={'catalog_id','operator_id','supplier_namespace','target_tv_hotel_id','target_native_id_for_comparison',
          'state','holds','safe_to_write_now'}
    def identity(row):
        if not isinstance(row,dict) or row.get('catalog_id') not in expected: fail('source3_row_identity')
        spec=expected[row['catalog_id']]
        if (type(row.get('operator_id')) is not int or type(row.get('target_tv_hotel_id')) is not int
                or tuple(row.get(k) for k in ('operator_id','supplier_namespace','target_tv_hotel_id','target_native_id_for_comparison'))!=spec
                or row.get('safe_to_write_now') is not False): fail('source3_row_identity')
        reasons=row.get('holds')
        if (not isinstance(reasons,list) or reasons!=sorted(set(reasons)) or any(r not in holds for r in reasons)):
            fail('source3_row_holds')
    preflight=data['preflight_rows'];evidence=data['evidence_rows'];responses=data['responses']
    if (not isinstance(preflight,list) or len(preflight) not in (0,3)
            or not isinstance(evidence,list) or len(evidence) not in (0,3)
            or not isinstance(responses,list) or len(responses)>2
            or (success and (len(preflight)!=3 or len(evidence)!=3))): fail('source3_partition')
    pre={}
    for row in preflight:
        identity(row)
        if (set(row)!=base or row['catalog_id'] in pre
                or row['state']!=('hold' if row['holds'] else 'eligible_for_source_evidence')): fail('source3_preflight_shape')
        pre[row['catalog_id']]=row
    response_index={}
    for response in responses:
        if not isinstance(response,dict) or set(response)!={'operator_id','catalog_ids','sha256'}: fail('source3_response_shape')
        op=response['operator_id']
        catalogs=sorted([c for c,r in pre.items() if r['operator_id']==op and not r['holds']],key=int)
        if (type(op) is not int or op not in (5,342) or op in response_index or not catalogs
                or response['catalog_ids']!=catalogs or not isinstance(response['sha256'],str)
                or not re.fullmatch(r'[a-f0-9]{64}',response['sha256'])): fail('source3_response_binding')
        response_index[op]=response['sha256']
    if success:
        eligible_ops={r['operator_id'] for r in preflight if not r['holds']}
        if set(response_index)!=eligible_ops or data['provider_http_calls']!=(1+len(eligible_ops) if eligible_ops else 0):
            fail('source3_http_binding')
    seen=set()
    for row in evidence:
        identity(row);cat=row['catalog_id']
        if (set(row)!=base|{'price_rows','native_ids','references','matches_target_native'}
                or cat in seen or cat not in pre or row['holds']!=pre[cat]['holds']): fail('source3_evidence_shape')
        seen.add(cat);ids=row['native_ids'];refs=row['references']
        if (type(row['price_rows']) is not int or not 0<=row['price_rows']<=2000
                or not isinstance(ids,list) or len(ids)>2000
                or any(not isinstance(n,str) or not re.fullmatch(r'[1-9][0-9]{0,31}',n) for n in ids)
                or ids!=sorted(set(ids),key=int) or not isinstance(refs,list)
                or not len(ids)<=len(refs)<=row['price_rows'] or (not ids and refs)):
            fail('source3_evidence_counts')
        expected_state=('preflight_hold' if row['holds'] else 'captured_single_native' if len(ids)==1
                        else 'captured_ambiguous_native' if ids else 'catalog_only' if row['price_rows'] else 'not_returned_in_context')
        target=row['target_native_id_for_comparison']
        if (row['state']!=expected_state or type(row['matches_target_native']) is not bool
                or row['matches_target_native']!=(not row['holds'] and target is not None and ids==[target])
                or (row['holds'] and (row['price_rows'] or ids or refs))): fail('source3_evidence_state')
        pointers=set()
        for ref in refs:
            op=row['operator_id']
            if (not isinstance(ref,dict) or set(ref)!={'private_file','sha256','json_pointer'}
                    or ref['private_file']!='operator-'+str(op)+'-page-1.json'
                    or ref['sha256']!=response_index.get(op) or not isinstance(ref['json_pointer'],str)
                    or not re.fullmatch(r'/PRICES/(?:0|[1-9][0-9]{0,3})',ref['json_pointer'])
                    or int(ref['json_pointer'].rsplit('/',1)[1])>=2000
                    or ref['json_pointer'] in pointers): fail('source3_reference_binding')
            pointers.add(ref['json_pointer'])
    if evidence and seen!=set(expected): fail('source3_evidence_partition')
    # Do not export provider/config exception text, even if the PHP sanitizer allowed it.
    summary={k:v for k,v in data.items() if k!='reason'}
    summary['reason_sha256']=hashlib.sha256(reason.encode()).hexdigest() if reason else None
    return summary

def run_match_source3(stage):
    if (operation!='int-andromeda-match-source3-native-current-20261001-v1'
            or payload.get('batch')!='source3-native-20261001'
            or type(payload.get('maximum_writes')) is not int or payload['maximum_writes']!=0
            or type(payload.get('provider_http_calls')) is not int or payload['provider_http_calls']!=3):
        fail('source3_fixed_scope')
    manifest=stage/'scripts/diagnostics/fixtures/hotel_match_source3_native_current_v1.json'
    runner=stage/'scripts/diagnostics/hotel_match_source3_native_current_v1.php'
    if (not safe_file(runner,2*1024*1024) or not safe_file(manifest,65536)
            or hashlib.sha256(manifest.read_bytes()).hexdigest()!='8af3a42bc63fb7b7eacb01df661cbf7ba6bcc83bdf9681adfc1e59c159b2cf85'):
        fail('source3_source_binding')
    parent=home/'.anytoour-match';root=parent/'operations'
    for folder in (parent,root):
        if not folder.is_dir() or folder.is_symlink() or folder.resolve()!=folder: fail('source3_private_root')
    child=root/operation
    if child.exists() or child.is_symlink(): fail('source3_child_exists_no_replay')
    reservation={'operation':operation,'source_sha':source,'batch':'source3-native-20261001',
                 'maximum_writes':0,'provider_http_calls':3,'state':'reserved_before_db_and_provider','reserved_at':int(time.time())}
    def exclusive(path,value):
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as stream:
            stream.write(json.dumps(value,sort_keys=True,separators=(',',':')).encode()+b'\n');stream.flush();os.fsync(stream.fileno())
        fd=os.open(path.parent,os.O_RDONLY|os.O_DIRECTORY)
        try: os.fsync(fd)
        finally: os.close(fd)
    # A consumed batch cannot be revived with another version or source head.
    exclusive(parent/'source3-native-current-batch-source3-native-20261001.json',reservation)
    child.mkdir(mode=0o700);exclusive(child/'reservation.json',reservation)
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(child),'MATCH_SOURCE_SHA':source})
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0',str(runner),'--acquire-source-evidence'],
                       cwd=project,env=env,capture_output=True,text=True,timeout=240)
    result_path=child/'result.json';receipt_path=child/'receipt.json'
    if (not safe_file(result_path,8388608) or not safe_file(receipt_path,65536)
            or run.stderr.strip() or len(run.stdout.encode())>65536): fail('source3_terminal_missing_no_replay')
    data=safe_json(result_path,8388608);receipt=safe_json(receipt_path,65536)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    summary=validate_match_source3(data,receipt,digest,source)
    successful=summary['state']=='completed_source3_native_current'
    if run.returncode!=(0 if successful else 2): fail('source3_exit_binding')
    stdout=json.loads(run.stdout)
    states={}
    for row in data['evidence_rows']: states[row['state']]=states.get(row['state'],0)+1
    if stdout!={'state':data['state'],'reason':data['reason'],'requested_sources':3,
                'provider_http_calls':data['provider_http_calls'],'evidence_states':states,'safe_to_write_now':False}:
        fail('source3_stdout_binding')
    return {'result_sha256':digest,'successful':successful,'no_replay':True,'summary':summary}
'''

REMOTE_SOURCE3_DISPATCH = r'''    if mode=='match-source3-native-current':
        source3=run_match_source3(stage)
        result['match_source3_native_current']=source3
        result['supplier_calls']=source3['summary']['provider_http_calls']
        result['database_reads']=source3['summary']['database_reads']
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before: fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete' if source3['successful'] else 'terminal_nonzero_no_replay'
'''


REMOTE_URL3_HANDLER = r'''
def run_match_native_absent3_saved_urls(stage):
    if (operation!='int-andromeda-match-native-absent3-saved-urls-20261008-v1'
            or payload.get('batch')!='native-absent3-saved-urls-20261008'
            or type(payload.get('maximum_writes')) is not int or payload['maximum_writes']!=0
            or type(payload.get('provider_http_calls')) is not int or payload['provider_http_calls']!=0):fail('native_absent3_saved_urls_scope')
    pins={'scripts/diagnostics/hotel_match_native_absent3_retained_fields_readonly_v1.py': 'ebfd1462f8fde062215b2fe71e6f5fc1cd236ad3834ffdf8fc6dd4d419a6135c', 'scripts/diagnostics/fixtures/hotel_match_native_absent3_retained_fields_readonly_v1.json': 'f43c431165137f817a6d04b4e925b7d382a3c2ff36ec139f3303d86998958e71', 'scripts/diagnostics/hotel_match_operator115_only2_retained_fields_readonly_v1.py': 'fe1b27a57155a3097e0073b6e8654aba5c2aa295ba9e851305f8c5ae08d4a3fc', 'scripts/diagnostics/hotel_match_nonbg7_unexported_fields_readonly_v1.py': '89f900e8e3342524419b4972cb27aad2b7e917c53e10df1db9475dcd852b1128', 'scripts/diagnostics/hotel_match_bg5_unexported_fields_readonly_v1.py': 'c9133f53e44bd8263f185626a78ce38013a0ee1ad758a477d7a1824b6213e196', 'scripts/diagnostics/hotel_match_nonbg5_retained_url_paths_readonly_v1.py': '8437cdb48cc571ff273cfdb95f9e8c5aca9cde5a6586cb93d99f60303961b356'}
    for relative,digest in pins.items():
        path=stage/relative
        if path.resolve()!=path or not safe_file(path,2*1024*1024) or hashlib.sha256(path.read_bytes()).hexdigest()!=digest:fail('native_absent3_saved_urls_source_binding')
    parent=home/'.anytoour-match';root=parent/'operations'
    for folder in (parent,root):
        if not folder.is_dir() or folder.is_symlink() or folder.resolve()!=folder:fail('native_absent3_saved_urls_private_root')
    child=root/operation
    if child.exists() or child.is_symlink():fail('native_absent3_saved_urls_child_exists_no_replay')
    reservation={'operation':operation,'source_sha':source,'batch':'native-absent3-saved-urls-20261008',
                 'provider_http_calls':0,'maximum_writes':0,'state':'reserved_before_saved_url_projection'}
    def exclusive(path,value):
        raw=json.dumps(value,sort_keys=True,separators=(',',':')).encode()+b'\n'
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
        with os.fdopen(fd,'wb') as stream:
            if stream.write(raw)!=len(raw):fail('native_absent3_saved_urls_reservation_short_write')
            stream.flush();os.fsync(stream.fileno())
        fd=os.open(path.parent,os.O_RDONLY|os.O_DIRECTORY)
        try:os.fsync(fd)
        finally:os.close(fd)
        if path.read_bytes()!=raw:fail('native_absent3_saved_urls_reservation_readback')
    exclusive(parent/'native-absent3-saved-urls-batch-20261008.json',reservation)
    child.mkdir(mode=0o700)
    fd=os.open(root,os.O_RDONLY|os.O_DIRECTORY)
    try:os.fsync(fd)
    finally:os.close(fd)
    exclusive(child/'reservation.json',reservation)
    runner=stage/'scripts/diagnostics/hotel_match_native_absent3_retained_fields_readonly_v1.py'
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_OPERATION_DIR':str(child),'MATCH_SOURCE_SHA':source})
    run=subprocess.run(['python3',str(runner),'--project-saved-urls'],cwd=project,env=env,capture_output=True,text=True,timeout=300)
    result_path=child/'result.json';receipt_path=child/'receipt.json';input_path=child/'current-input.json'
    if (not safe_file(result_path,1024*1024) or not safe_file(receipt_path,65536)
            or not safe_file(input_path,4*1024*1024) or run.stderr.strip()
            or len(run.stdout.encode())>65536):fail('native_absent3_saved_urls_terminal_missing_no_replay')
    import importlib.util
    sys.path.insert(0,str(runner.parent))
    spec=importlib.util.spec_from_file_location('checked_native_absent3_saved_urls_source',runner)
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
    data=safe_json(result_path,1024*1024);receipt=safe_json(receipt_path,65536)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    input_digest=hashlib.sha256(input_path.read_bytes()).hexdigest()
    private_raw=module.n.file_bytes(input_path,4*1024*1024)
    if hashlib.sha256(private_raw).hexdigest()!=input_digest:fail('native_absent3_saved_urls_private_snapshot_drift')
    try:
        module.validate_saved_urls(data,receipt,source,module.n.parsed(private_raw),child)
    except Exception:fail('native_absent3_saved_urls_original_or_terminal_binding')
    if receipt.get('result_sha256')!=digest or data.get('private_input_sha256')!=input_digest:fail('native_absent3_saved_urls_terminal_digest')
    if run.returncode!=0:fail('native_absent3_saved_urls_exit_binding')
    try:stdout=module.n.parsed(run.stdout.encode())
    except Exception:fail('native_absent3_saved_urls_stdout_binding')
    keys=('state','rows_examined','accepted','written')
    if set(stdout)!=set(keys) or any(type(stdout[k]) is not type(data[k]) or stdout[k]!=data[k] for k in keys):fail('native_absent3_saved_urls_stdout_binding')
    return {'result_sha256':digest,'private_input_sha256':input_digest,'successful':True,'no_replay':True,'summary':data}

'''

REMOTE_URL3_DISPATCH = r'''    if mode=='match-native-absent3-saved-urls-readonly':
        lane=run_match_native_absent3_saved_urls(stage)
        result['match_native_absent3_saved_urls']=lane
        result['supplier_calls']=0
        result['database_reads']=0
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''

REMOTE_NA3_HANDLER = r'''
def run_match_native_absent3_fields(stage):
    if (operation!='int-andromeda-match-native-absent3-retained-fields-20261008-v1'
            or payload.get('batch')!='native-absent3-retained-fields-20261008'
            or type(payload.get('maximum_writes')) is not int or payload['maximum_writes']!=0
            or type(payload.get('provider_http_calls')) is not int or payload['provider_http_calls']!=0):fail('native_absent3_scope')
    pins={'scripts/diagnostics/hotel_match_native_absent3_retained_fields_readonly_v1.py': '1eb5d0fca5a3c85e7a8e7b6dad6cc8484bcd3580d547c0d00467f218502f4a06', 'scripts/diagnostics/fixtures/hotel_match_native_absent3_retained_fields_readonly_v1.json': 'f43c431165137f817a6d04b4e925b7d382a3c2ff36ec139f3303d86998958e71', 'scripts/diagnostics/hotel_match_operator115_only2_retained_fields_readonly_v1.py': 'fe1b27a57155a3097e0073b6e8654aba5c2aa295ba9e851305f8c5ae08d4a3fc', 'scripts/diagnostics/hotel_match_nonbg7_unexported_fields_readonly_v1.py': '89f900e8e3342524419b4972cb27aad2b7e917c53e10df1db9475dcd852b1128', 'scripts/diagnostics/hotel_match_bg5_unexported_fields_readonly_v1.py': 'c9133f53e44bd8263f185626a78ce38013a0ee1ad758a477d7a1824b6213e196'}
    for relative,digest in pins.items():
        path=stage/relative
        if path.resolve()!=path or not safe_file(path,2*1024*1024) or hashlib.sha256(path.read_bytes()).hexdigest()!=digest:fail('native_absent3_source_binding')
    parent=home/'.anytoour-match';root=parent/'operations'
    for folder in (parent,root):
        if not folder.is_dir() or folder.is_symlink() or folder.resolve()!=folder:fail('native_absent3_private_root')
    child=root/operation
    if child.exists() or child.is_symlink():fail('native_absent3_child_exists_no_replay')
    reservation={'operation':operation,'source_sha':source,'batch':'native-absent3-retained-fields-20261008',
                 'provider_http_calls':0,'maximum_writes':0,'state':'reserved_before_retained_read'}
    def exclusive(path,value):
        raw=json.dumps(value,sort_keys=True,separators=(',',':')).encode()+b'\n'
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
        with os.fdopen(fd,'wb') as stream:
            if stream.write(raw)!=len(raw):fail('native_absent3_reservation_short_write')
            stream.flush();os.fsync(stream.fileno())
        fd=os.open(path.parent,os.O_RDONLY|os.O_DIRECTORY)
        try:os.fsync(fd)
        finally:os.close(fd)
        if path.read_bytes()!=raw:fail('native_absent3_reservation_readback')
    exclusive(parent/'native-absent3-fields-batch-20261008.json',reservation)
    child.mkdir(mode=0o700)
    fd=os.open(root,os.O_RDONLY|os.O_DIRECTORY)
    try:os.fsync(fd)
    finally:os.close(fd)
    exclusive(child/'reservation.json',reservation)
    # Same existing stock config-path resolver as retained v77. No secret is projected.
    config=project/'_preview/search3-anex-candidate/.andromeda-private.php'
    if config.resolve()!=config or not safe_file(config,65536):fail('native_absent3_config_missing_no_replay')
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    php="$c=require $argv[1];$p=$c['catalog_path']??null;if(!is_string($p)||$p==='')exit(2);echo dirname($p);"
    q=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0','-r',php,str(config)],
                     env=env,capture_output=True,text=True,timeout=20)
    if q.returncode or q.stderr.strip() or not q.stdout or len(q.stdout.encode())>4096:fail('native_absent3_config_root_no_replay')
    cache=pathlib.Path(q.stdout)
    if (not cache.is_absolute() or cache.resolve()!=cache or cache.is_symlink() or not cache.is_dir()
            or not cache.is_relative_to(home) or cache.is_relative_to(home/'www')):fail('native_absent3_cache_root_no_replay')
    runner=stage/'scripts/diagnostics/hotel_match_native_absent3_retained_fields_readonly_v1.py'
    manifest=stage/'scripts/diagnostics/fixtures/hotel_match_native_absent3_retained_fields_readonly_v1.json'
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_SOURCE_ROOT':str(stage),'MATCH_CACHE_ROOT':str(cache),
                'MATCH_OPERATION_DIR':str(child),'MATCH_MANIFEST_PATH':str(manifest),'MATCH_SOURCE_SHA':source})
    run=subprocess.run(['python3',str(runner),'--execute'],cwd=project,env=env,capture_output=True,text=True,timeout=300)
    result_path=child/'result.json';receipt_path=child/'receipt.json';input_path=child/'current-input.json'
    if (not safe_file(result_path,2*1024*1024) or not safe_file(receipt_path,65536)
            or not safe_file(input_path,4*1024*1024) or run.stderr.strip()
            or len(run.stdout.encode())>65536):fail('native_absent3_terminal_missing_no_replay')
    import importlib.util
    sys.path.insert(0,str(runner.parent))
    spec=importlib.util.spec_from_file_location('checked_native_absent3_fields_source',runner)
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
    data=safe_json(result_path,2*1024*1024);receipt=safe_json(receipt_path,65536)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    input_digest=hashlib.sha256(input_path.read_bytes()).hexdigest()
    private_raw=module.n.file_bytes(input_path,4*1024*1024)
    if hashlib.sha256(private_raw).hexdigest()!=input_digest:fail('native_absent3_private_snapshot_drift')
    private_data=module.n.parsed(private_raw)
    try:
        module.verify_originals(child,private_data,module.manifest())
        module.validate_result(data,receipt,source,private_data)
    except Exception:fail('native_absent3_original_or_terminal_binding')
    if run.returncode!=0:fail('native_absent3_exit_binding')
    try:stdout=module.n.parsed(run.stdout.encode())
    except Exception:fail('native_absent3_stdout_binding')
    keys=('state','rows_examined','accepted','written')
    if set(stdout)!=set(keys) or any(type(stdout[k]) is not type(data[k]) or stdout[k]!=data[k] for k in keys):fail('native_absent3_stdout_binding')
    return {'result_sha256':digest,'private_input_sha256':input_digest,'successful':True,'no_replay':True,'summary':data}

'''

REMOTE_NA3_DISPATCH = r'''    if mode=='match-native-absent3-retained-fields-readonly':
        lane=run_match_native_absent3_fields(stage)
        result['match_native_absent3_retained_fields']=lane
        result['supplier_calls']=0
        result['database_reads']=0
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''

REMOTE_OP1152_HANDLER = r'''
def validate_match_operator115_only2_fields(data,receipt,digest,input_digest,expected_source,validate_source):
    fixed={'schema':'match-operator115-only2-retained-fields-result/1',
           'operation':'int-andromeda-match-operator115-only2-retained-fields-20261007-v1',
           'batch':'operator115-only2-retained-20261007','source_sha':expected_source,
           'provider_http_calls':0,'physical_http_attempts':0,'database_writes':0,
           'mapping_writes':0,'booking_calls':0,'lead_calls':0,'accepted':0,'written':0,
           'safe_to_write_now':False,'acceptance_evaluated':False,
           'global_uniqueness_evaluated':False,'namespace_bridge_verified':False,'no_replay':True,
           'source_namespace':'operator_115','requested_rows':2}
    if (not isinstance(data,dict) or not isinstance(receipt,dict)
            or any(type(data.get(k)) is not type(v) or data.get(k)!=v for k,v in fixed.items())
            or type(data.get('database_reads')) is not int or data['database_reads']!=0
            or data.get('private_input_sha256')!=input_digest
            or receipt.get('private_input_sha256')!=input_digest
            or receipt.get('result_sha256')!=digest):fail('operator115_only2_fields_terminal_binding')
    for k,v in receipt.items():
        if k not in ('result_sha256','private_input_sha256') and (k not in data or type(v) is not type(data[k]) or v!=data[k]):fail('operator115_only2_fields_receipt_binding')
    if set(receipt)!={'operation','batch','source_sha','state','result_sha256','private_input_sha256',
                     'provider_http_calls','physical_http_attempts','database_reads','database_writes',
                     'mapping_writes','booking_calls','lead_calls','accepted','written',
                     'safe_to_write_now','acceptance_evaluated','global_uniqueness_evaluated','namespace_bridge_verified','no_replay'}:fail('operator115_only2_fields_receipt_shape')
    try:validate_source(data)
    except Exception:fail('operator115_only2_fields_source_validation')
    if data['state'] not in ('completed_read_only_operator115_only2_fields','completed_read_only_operator115_only2_fields_incomplete','terminal_failed_no_replay'):fail('operator115_only2_fields_terminal_state')
    return data

def run_match_operator115_only2_fields(stage):
    if (operation!='int-andromeda-match-operator115-only2-retained-fields-20261007-v1'
            or payload.get('batch')!='operator115-only2-retained-20261007'
            or type(payload.get('maximum_writes')) is not int or payload['maximum_writes']!=0
            or type(payload.get('provider_http_calls')) is not int or payload['provider_http_calls']!=0):fail('operator115_only2_fields_scope')
    runner=stage/'scripts/diagnostics/hotel_match_operator115_only2_retained_fields_readonly_v1.py'
    manifest=stage/'scripts/diagnostics/fixtures/hotel_match_operator115_only2_retained_fields_readonly_v1.json'
    if (not safe_file(runner,2*1024*1024) or not safe_file(manifest,65536)
            or hashlib.sha256(manifest.read_bytes()).hexdigest()!='40df08d71eb09f6185bdb31213686e4771262871205c3329025a75150f1f11df'):fail('operator115_only2_fields_source_binding')
    pins={"scripts/diagnostics/hotel_match_operator115_only2_retained_fields_readonly_v1.py":"fe1b27a57155a3097e0073b6e8654aba5c2aa295ba9e851305f8c5ae08d4a3fc","scripts/diagnostics/fixtures/hotel_match_operator115_only2_retained_fields_readonly_v1.json":"40df08d71eb09f6185bdb31213686e4771262871205c3329025a75150f1f11df","scripts/diagnostics/hotel_match_nonbg7_unexported_fields_readonly_v1.py":"89f900e8e3342524419b4972cb27aad2b7e917c53e10df1db9475dcd852b1128","scripts/diagnostics/hotel_match_bg5_unexported_fields_readonly_v1.py":"c9133f53e44bd8263f185626a78ce38013a0ee1ad758a477d7a1824b6213e196"}
    for relative,digest in pins.items():
        path=stage/relative
        if path.resolve()!=path or not safe_file(path,2*1024*1024) or hashlib.sha256(path.read_bytes()).hexdigest()!=digest:fail('operator115_only2_fields_source_binding')
    parent=home/'.anytoour-match';root=parent/'operations'
    for folder in (parent,root):
        if not folder.is_dir() or folder.is_symlink() or folder.resolve()!=folder:fail('operator115_only2_fields_private_root')
    child=root/operation
    if child.exists() or child.is_symlink():fail('operator115_only2_fields_child_exists_no_replay')
    reservation={'operation':operation,'source_sha':source,'batch':'operator115-only2-retained-20261007',
                 'provider_http_calls':0,'maximum_writes':0,'state':'reserved_before_retained_read'}
    def exclusive(path,value):
        raw=json.dumps(value,sort_keys=True,separators=(',',':')).encode()+b'\n'
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as stream:
            if stream.write(raw)!=len(raw):fail('operator115_only2_fields_reservation_short_write')
            stream.flush();os.fsync(stream.fileno())
        fd=os.open(path.parent,os.O_RDONLY|os.O_DIRECTORY)
        try:os.fsync(fd)
        finally:os.close(fd)
        if path.read_bytes()!=raw:fail('operator115_only2_fields_reservation_readback')
    exclusive(parent/'operator115_only2-retained-fields-batch-20261007.json',reservation)
    child.mkdir(mode=0o700)
    fd=os.open(root,os.O_RDONLY|os.O_DIRECTORY)
    try:os.fsync(fd)
    finally:os.close(fd)
    exclusive(child/'reservation.json',reservation)
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_SOURCE_ROOT':str(stage),
                'MATCH_OPERATION_DIR':str(child),'MATCH_MANIFEST_PATH':str(manifest),'MATCH_SOURCE_SHA':source})
    run=subprocess.run(['python3',str(runner),'--execute'],cwd=project,env=env,capture_output=True,text=True,timeout=300)
    result_path=child/'result.json';receipt_path=child/'receipt.json';input_path=child/'current-input.json'
    if (not safe_file(result_path,2*1024*1024) or not safe_file(receipt_path,65536)
            or not safe_file(input_path,6*1024*1024) or run.stderr.strip()
            or len(run.stdout.encode())>65536):fail('operator115_only2_fields_terminal_missing_no_replay')
    import importlib.util
    spec=importlib.util.spec_from_file_location('checked_operator115_only2_fields_source',runner)
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
    data=safe_json(result_path,2*1024*1024);receipt=safe_json(receipt_path,65536)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    input_digest=hashlib.sha256(input_path.read_bytes()).hexdigest()
    private_raw=module.n.file_bytes(input_path,6*1024*1024)
    if hashlib.sha256(private_raw).hexdigest()!=input_digest:fail('operator115_only2_fields_private_snapshot_drift')
    private_data=module.n.parsed(private_raw)
    summary=validate_match_operator115_only2_fields(data,receipt,digest,input_digest,source,
        lambda value:module.validate_result(value,receipt,source,private_data))
    if summary['original_bytes_preserved']:
        original_path=child/'retained-original.json'
        if not safe_file(original_path,2*1024*1024):fail('operator115_only2_fields_original_missing')
        original_raw=module.n.file_bytes(original_path,2*1024*1024)
        if (len(original_raw)!=summary['raw_bytes_read']
                or hashlib.sha256(original_raw).hexdigest()!=summary['original_source_sha256']):fail('operator115_only2_fields_original_binding')
        if summary['state']!='terminal_failed_no_replay':
            original_data=module.n.parsed(original_raw)
            for entry,spec in zip(private_data['rows'],module.manifest()['rows']):
                try:original_row=module.n.pointer(original_data,spec['json_pointer'])
                except Exception:original_row=None
                if not module.n.equal_typed(entry['original_row'],original_row):fail('operator115_only2_fields_original_row_binding')
    successful=summary['state'] in ('completed_read_only_operator115_only2_fields','completed_read_only_operator115_only2_fields_incomplete')
    if run.returncode!=(0 if successful else 2):fail('operator115_only2_fields_exit_binding')
    try:stdout=module.n.parsed(run.stdout.encode())
    except Exception:fail('operator115_only2_fields_stdout_binding')
    stdout_keys=('state','rows_examined','accepted','written')
    if set(stdout)!=set(stdout_keys) or any(type(stdout[k]) is not type(data[k]) or stdout[k]!=data[k] for k in stdout_keys):fail('operator115_only2_fields_stdout_binding')
    return {'result_sha256':digest,'private_input_sha256':input_digest,'successful':successful,'no_replay':True,'summary':summary}

'''

REMOTE_OP1152_DISPATCH = r'''    if mode=='match-operator115-only2-retained-fields-readonly':
        lane=run_match_operator115_only2_fields(stage)
        result['match_operator115_only2_retained_fields']=lane
        result['supplier_calls']=0
        result['database_reads']=0
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete' if lane['successful'] else 'terminal_nonzero_no_replay'
'''

REMOTE_ALIAS3_HANDLER = r'''
def validate_match_alias3_fields(data,receipt,digest,input_digest,expected_source,validate_source):
    fixed={'schema':'match-alias3-retained-fields-result/1',
           'operation':'int-andromeda-match-alias3-retained-fields-20261007-v1',
           'batch':'alias3-retained-fields-20261007','source_sha':expected_source,
           'provider_http_calls':0,'physical_http_attempts':0,'database_writes':0,
           'mapping_writes':0,'booking_calls':0,'lead_calls':0,'accepted':0,'written':0,
           'safe_to_write_now':False,'acceptance_evaluated':False,
           'global_uniqueness_evaluated':False,'namespace_bridge_verified':False,'no_replay':True,
           'source_namespace':'operator_115','requested_rows':3}
    if (not isinstance(data,dict) or not isinstance(receipt,dict)
            or any(type(data.get(k)) is not type(v) or data.get(k)!=v for k,v in fixed.items())
            or type(data.get('database_reads')) is not int or data['database_reads']!=0
            or data.get('private_input_sha256')!=input_digest
            or receipt.get('private_input_sha256')!=input_digest
            or receipt.get('result_sha256')!=digest):fail('alias3_fields_terminal_binding')
    for k,v in receipt.items():
        if k not in ('result_sha256','private_input_sha256') and (k not in data or type(v) is not type(data[k]) or v!=data[k]):fail('alias3_fields_receipt_binding')
    if set(receipt)!={'operation','batch','source_sha','state','result_sha256','private_input_sha256',
                     'provider_http_calls','physical_http_attempts','database_reads','database_writes',
                     'mapping_writes','booking_calls','lead_calls','accepted','written',
                     'safe_to_write_now','acceptance_evaluated','global_uniqueness_evaluated','namespace_bridge_verified','no_replay'}:fail('alias3_fields_receipt_shape')
    try:validate_source(data)
    except Exception:fail('alias3_fields_source_validation')
    if data['state'] not in ('completed_read_only_alias3_fields','completed_read_only_alias3_fields_incomplete','terminal_failed_no_replay'):fail('alias3_fields_terminal_state')
    return data

def run_match_alias3_fields(stage):
    if (operation!='int-andromeda-match-alias3-retained-fields-20261007-v1'
            or payload.get('batch')!='alias3-retained-fields-20261007'
            or type(payload.get('maximum_writes')) is not int or payload['maximum_writes']!=0
            or type(payload.get('provider_http_calls')) is not int or payload['provider_http_calls']!=0):fail('alias3_fields_scope')
    runner=stage/'scripts/diagnostics/hotel_match_alias3_retained_fields_readonly_v1.py'
    manifest=stage/'scripts/diagnostics/fixtures/hotel_match_alias3_retained_fields_readonly_v1.json'
    if (not safe_file(runner,2*1024*1024) or not safe_file(manifest,65536)
            or hashlib.sha256(manifest.read_bytes()).hexdigest()!='758f38da8a9d57f9e770a8b422f4115ea28650bc0fe97164e96c319c467cc36a'):fail('alias3_fields_source_binding')
    pins={"scripts/diagnostics/hotel_match_alias3_retained_fields_readonly_v1.py":"47a5d0a03a85fbeae046eddc6ca8033ac30500c0491aefe362d7aa11f8cfb695","scripts/diagnostics/fixtures/hotel_match_alias3_retained_fields_readonly_v1.json":"758f38da8a9d57f9e770a8b422f4115ea28650bc0fe97164e96c319c467cc36a","scripts/diagnostics/hotel_match_nonbg7_unexported_fields_readonly_v1.py":"89f900e8e3342524419b4972cb27aad2b7e917c53e10df1db9475dcd852b1128","scripts/diagnostics/hotel_match_bg5_unexported_fields_readonly_v1.py":"c9133f53e44bd8263f185626a78ce38013a0ee1ad758a477d7a1824b6213e196"}
    for relative,digest in pins.items():
        path=stage/relative
        if path.resolve()!=path or not safe_file(path,2*1024*1024) or hashlib.sha256(path.read_bytes()).hexdigest()!=digest:fail('alias3_fields_source_binding')
    parent=home/'.anytoour-match';root=parent/'operations'
    for folder in (parent,root):
        if not folder.is_dir() or folder.is_symlink() or folder.resolve()!=folder:fail('alias3_fields_private_root')
    child=root/operation
    if child.exists() or child.is_symlink():fail('alias3_fields_child_exists_no_replay')
    reservation={'operation':operation,'source_sha':source,'batch':'alias3-retained-fields-20261007',
                 'provider_http_calls':0,'maximum_writes':0,'state':'reserved_before_retained_read'}
    def exclusive(path,value):
        raw=json.dumps(value,sort_keys=True,separators=(',',':')).encode()+b'\n'
        fd=os.open(path,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
        with os.fdopen(fd,'wb') as stream:
            if stream.write(raw)!=len(raw):fail('alias3_fields_reservation_short_write')
            stream.flush();os.fsync(stream.fileno())
        fd=os.open(path.parent,os.O_RDONLY|os.O_DIRECTORY)
        try:os.fsync(fd)
        finally:os.close(fd)
        if path.read_bytes()!=raw:fail('alias3_fields_reservation_readback')
    exclusive(parent/'alias3-retained-fields-batch-20261007.json',reservation)
    child.mkdir(mode=0o700)
    fd=os.open(root,os.O_RDONLY|os.O_DIRECTORY)
    try:os.fsync(fd)
    finally:os.close(fd)
    exclusive(child/'reservation.json',reservation)
    env={key:os.environ[key] for key in ('PATH','HOME','LANG','LC_ALL') if key in os.environ}
    env.update({'ANYTOUR_ROOT':str(project),'MATCH_SOURCE_ROOT':str(stage),
                'MATCH_OPERATION_DIR':str(child),'MATCH_MANIFEST_PATH':str(manifest),'MATCH_SOURCE_SHA':source})
    run=subprocess.run(['python3',str(runner),'--execute'],cwd=project,env=env,capture_output=True,text=True,timeout=300)
    result_path=child/'result.json';receipt_path=child/'receipt.json';input_path=child/'current-input.json'
    if (not safe_file(result_path,2*1024*1024) or not safe_file(receipt_path,65536)
            or not safe_file(input_path,6*1024*1024) or run.stderr.strip()
            or len(run.stdout.encode())>65536):fail('alias3_fields_terminal_missing_no_replay')
    import importlib.util
    spec=importlib.util.spec_from_file_location('checked_alias3_fields_source',runner)
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
    data=safe_json(result_path,2*1024*1024);receipt=safe_json(receipt_path,65536)
    digest=hashlib.sha256(result_path.read_bytes()).hexdigest()
    input_digest=hashlib.sha256(input_path.read_bytes()).hexdigest()
    private_raw=module.n.file_bytes(input_path,6*1024*1024)
    if hashlib.sha256(private_raw).hexdigest()!=input_digest:fail('alias3_fields_private_snapshot_drift')
    private_data=module.n.parsed(private_raw)
    summary=validate_match_alias3_fields(data,receipt,digest,input_digest,source,
        lambda value:module.validate_result(value,receipt,source,private_data))
    if summary['original_bytes_preserved']:
        original_path=child/'retained-original.json'
        if not safe_file(original_path,2*1024*1024):fail('alias3_fields_original_missing')
        original_raw=module.n.file_bytes(original_path,2*1024*1024)
        if (len(original_raw)!=summary['raw_bytes_read']
                or hashlib.sha256(original_raw).hexdigest()!=summary['original_source_sha256']):fail('alias3_fields_original_binding')
        if summary['state']!='terminal_failed_no_replay':
            original_data=module.n.parsed(original_raw)
            for entry,spec in zip(private_data['rows'],module.manifest()['rows']):
                try:original_row=module.n.pointer(original_data,spec['json_pointer'])
                except Exception:original_row=None
                if not module.n.equal_typed(entry['original_row'],original_row):fail('alias3_fields_original_row_binding')
    successful=summary['state'] in ('completed_read_only_alias3_fields','completed_read_only_alias3_fields_incomplete')
    if run.returncode!=(0 if successful else 2):fail('alias3_fields_exit_binding')
    try:stdout=module.n.parsed(run.stdout.encode())
    except Exception:fail('alias3_fields_stdout_binding')
    stdout_keys=('state','rows_examined','accepted','written')
    if set(stdout)!=set(stdout_keys) or any(type(stdout[k]) is not type(data[k]) or stdout[k]!=data[k] for k in stdout_keys):fail('alias3_fields_stdout_binding')
    return {'result_sha256':digest,'private_input_sha256':input_digest,'successful':successful,'no_replay':True,'summary':summary}

'''

REMOTE_ALIAS3_METADATA_HANDLER = r'''
alias3_terminal_target_pins={"scripts/diagnostics/hotel_match_alias3_retained_fields_readonly_v1.py":"f47fa828f1f6aa6393376fb800850ea405a4949d579acefa67ca026fab138002","scripts/diagnostics/fixtures/hotel_match_alias3_retained_fields_readonly_v1.json":"758f38da8a9d57f9e770a8b422f4115ea28650bc0fe97164e96c319c467cc36a","scripts/diagnostics/hotel_match_nonbg7_unexported_fields_readonly_v1.py":"89f900e8e3342524419b4972cb27aad2b7e917c53e10df1db9475dcd852b1128","scripts/diagnostics/hotel_match_bg5_unexported_fields_readonly_v1.py":"c9133f53e44bd8263f185626a78ce38013a0ee1ad758a477d7a1824b6213e196"}

def run_match_alias3_terminal_metadata(stage):
    # This operation never executes the consumed reader or opens supplier/capture bodies.
    if (operation!='int-andromeda-match-alias3-terminal-metadata-20261007-v1'
            or payload.get('batch')!='alias3-terminal-metadata-20261007'
            or type(payload.get('maximum_writes')) is not int or payload['maximum_writes']!=0
            or type(payload.get('provider_http_calls')) is not int or payload['provider_http_calls']!=0):fail('alias3_metadata_scope')
    pins={"scripts/diagnostics/hotel_match_alias3_retained_fields_readonly_v1.py":"47a5d0a03a85fbeae046eddc6ca8033ac30500c0491aefe362d7aa11f8cfb695","scripts/diagnostics/fixtures/hotel_match_alias3_retained_fields_readonly_v1.json":"758f38da8a9d57f9e770a8b422f4115ea28650bc0fe97164e96c319c467cc36a","scripts/diagnostics/hotel_match_nonbg7_unexported_fields_readonly_v1.py":"89f900e8e3342524419b4972cb27aad2b7e917c53e10df1db9475dcd852b1128","scripts/diagnostics/hotel_match_bg5_unexported_fields_readonly_v1.py":"c9133f53e44bd8263f185626a78ce38013a0ee1ad758a477d7a1824b6213e196"}
    for relative,digest in pins.items():
        path=stage/relative
        if path.resolve()!=path or not safe_file(path,2*1024*1024) or hashlib.sha256(path.read_bytes()).hexdigest()!=digest:fail('alias3_metadata_source_binding')
    import importlib.util,stat
    runner=stage/'scripts/diagnostics/hotel_match_alias3_retained_fields_readonly_v1.py'
    spec=importlib.util.spec_from_file_location('checked_alias3_metadata_helpers',runner)
    module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
    parent=home/'.anytoour-match';root=parent/'operations'
    old_operation='int-andromeda-match-alias3-retained-fields-20261007-v1'
    old_source='c35d9972c14d7f19aa8539e214217a8c0d5f2a7d'
    old_mode='match-alias3-retained-fields-readonly';old_batch='alias3-retained-fields-20261007'
    old_child=root/old_operation;old_outer=home/'.anytoour-int-executor'/old_operation
    if project!=home/'www'/'anytoour.ru':fail('alias3_metadata_project_layout')
    for folder in (parent,root,old_child,old_outer.parent,old_outer):
        if (not folder.is_dir() or folder.is_symlink() or folder.resolve()!=folder
                or stat.S_IMODE(folder.stat().st_mode)!=0o700):fail('alias3_metadata_private_root')
    child=root/operation;marker=parent/'alias3-terminal-metadata-batch-20261007.json'
    if child.exists() or child.is_symlink() or marker.exists() or marker.is_symlink():fail('alias3_metadata_consumed_no_replay')
    reservation={'operation':operation,'source_sha':source,'batch':'alias3-terminal-metadata-20261007',
                 'provider_http_calls':0,'maximum_writes':0,'state':'reserved_before_terminal_metadata_read'}
    module.n.save(marker,reservation);child.mkdir(mode=0o700)
    fd=os.open(root,os.O_RDONLY|os.O_DIRECTORY)
    try:os.fsync(fd)
    finally:os.close(fd)
    module.n.save(child/'reservation.json',reservation)
    targets=(('outer_reservation',old_outer/'reservation.json',65536),
             ('installed_source',old_outer/'installed-source.json',131072),
             ('outer_result',old_outer/'result.json',1048576),
             ('consumed_batch',parent/'alias3-retained-fields-batch-20261007.json',65536),
             ('child_reservation',old_child/'reservation.json',65536))
    originals={};records=[];total=0
    for label,path,cap in targets:
        if path.is_symlink() or not path.is_file() or stat.S_IMODE(path.stat().st_mode)!=0o600:fail('alias3_metadata_file_security')
        raw=module.n.file_bytes(path,cap);total+=len(raw)
        if total>2*1024*1024:fail('alias3_metadata_total_cap')
        digest=module.durable_bytes(child/('metadata-original-'+label+'.json'),raw)
        originals[label]=raw;records.append({'role':label,'sha256':digest,'size_bytes':len(raw)})
    # Preserve all exact originals before even parsing the first identity/receipt record.
    values={label:module.n.parsed(raw) for label,raw in originals.items()}
    outer_res=values['outer_reservation']
    if (set(outer_res)!={'operation_id','source_sha','mode','reserved_at'}
            or type(outer_res['reserved_at']) is not int or outer_res['reserved_at']<=0):fail('alias3_metadata_outer_reservation')
    module.n.require_fields(outer_res,{'operation_id':old_operation,'source_sha':old_source,'mode':old_mode})
    installed=values['installed_source']
    if (set(installed)!={'source_sha','files'} or installed.get('source_sha')!=old_source
            or not isinstance(installed['files'],dict) or len(installed['files'])<20):fail('alias3_metadata_installed_source')
    for relative,digest in installed['files'].items():
        if (not isinstance(relative,str) or not re.fullmatch(r'[A-Za-z0-9_./-]+',relative)
                or relative.startswith('/') or '..' in relative.split('/')
                or not isinstance(digest,str) or not re.fullmatch(r'[0-9a-f]{64}',digest)):fail('alias3_metadata_installed_source')
    module.n.require_fields(installed['files'],alias3_terminal_target_pins)
    expected_old_result={'schema_version':1,'operation_id':old_operation,'source_sha':old_source,'mode':old_mode,
        'status':'unknown_no_replay','reason':'alias3_fields_terminal_missing_no_replay',
        'supplier_calls':'unknown','database_writes':'unknown','booking_calls':0,'lead_calls':0,
        'production_before':{'index.php':'80e993e80a4c3e11612187e90ffd8cfdadce08657981db19429f882ed2de4c6f',
                             'v2/index.php':None,'v2/api-v2.php':None,'v2/lead-adapter-v2.php':None}}
    if not module.n.equal_typed(values['outer_result'],expected_old_result):fail('alias3_metadata_outer_result')
    expected_old_res={'operation':old_operation,'source_sha':old_source,'batch':old_batch,
        'provider_http_calls':0,'maximum_writes':0,'state':'reserved_before_retained_read'}
    for label in ('consumed_batch','child_reservation'):
        if not module.n.equal_typed(values[label],expected_old_res):fail('alias3_metadata_consumed_binding')
    presence={}
    for name in ('retained-original.json','current-input.json','result.json','receipt.json'):
        path=old_child/name
        if path.is_symlink() or (path.exists() and not path.is_file()):fail('alias3_metadata_capture_path_security')
        presence[name]=path.exists()  # No capture-body open or read is permitted.
    private_data={'schema':'match-alias3-terminal-metadata-private-input/1','operation':operation,
        'batch':'alias3-terminal-metadata-20261007','source_sha':source,'target_operation':old_operation,
        'target_source_sha':old_source,'metadata_records':records,'metadata_values':values,'capture_file_presence':presence}
    input_digest=module.n.save(child/'current-input.json',private_data)
    data={'schema':'match-alias3-terminal-metadata-result/1','operation':operation,'source_sha':source,
        'batch':'alias3-terminal-metadata-20261007','state':'completed_read_only_terminal_metadata',
        'target_operation':old_operation,'target_source_sha':old_source,
        'target_status':'unknown_no_replay','target_reason':'alias3_fields_terminal_missing_no_replay',
        'target_supplier_calls':'unknown','target_database_writes':'unknown','capture_file_presence':presence,
        'metadata_records':records,'metadata_files_read':5,'metadata_bytes_read':total,
        'private_input_sha256':input_digest,'original_raw_files_read':0,'old_capture_bodies_read':0,
        'old_operation_replayed':False,'provider_http_calls':0,'physical_http_attempts':0,
        'database_reads':0,'database_writes':0,'mapping_writes':0,'booking_calls':0,'lead_calls':0,
        'accepted':0,'written':0,'acceptance_evaluated':False,'current_readiness':'not_evaluated','no_replay':True}
    digest=module.n.save(child/'result.json',data)
    receipt={key:data[key] for key in ('operation','batch','source_sha','state','target_operation','target_source_sha',
        'target_status','private_input_sha256','provider_http_calls','physical_http_attempts','database_reads',
        'database_writes','mapping_writes','booking_calls','lead_calls','accepted','written','acceptance_evaluated','no_replay')}
    receipt['result_sha256']=digest;module.n.save(child/'receipt.json',receipt)
    def checked(path,limit,expected,sha=None):
        raw=module.n.file_bytes(path,limit)
        if (sha is not None and hashlib.sha256(raw).hexdigest()!=sha) or not module.n.equal_typed(module.n.parsed(raw),expected):fail('alias3_metadata_terminal_readback')
    checked(child/'current-input.json',2*1024*1024,private_data,input_digest)
    checked(child/'result.json',65536,data,digest);checked(child/'receipt.json',65536,receipt)
    for record in records:
        raw=module.n.file_bytes(child/('metadata-original-'+record['role']+'.json'),record['size_bytes'])
        if hashlib.sha256(raw).hexdigest()!=record['sha256']:fail('alias3_metadata_original_readback')
    return {'result_sha256':digest,'private_input_sha256':input_digest,'successful':True,'no_replay':True,'summary':data}
'''

REMOTE_ALIAS3_DISPATCH = r'''    if mode=='match-alias3-retained-fields-readonly':
        lane=run_match_alias3_fields(stage)
        result['match_alias3_retained_fields_readonly']=lane
        result['supplier_calls']=0
        result['database_reads']=0
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete' if lane['successful'] else 'terminal_nonzero_no_replay'

    if mode=='match-alias3-terminal-metadata-readback':
        lane=run_match_alias3_terminal_metadata(stage)
        result['match_alias3_terminal_metadata']=lane
        result['supplier_calls']=0
        result['database_reads']=0
        result['database_writes']=0
        result['mapping_writes']=0
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'

'''


def remote_with_primary(core, proof: bool = False, native: bool = False, guarded: bool = False, bg: bool = False,
                        shams_geo: bool = False, shams_geo_readback: bool = False, shams_write: bool = False,
                        target_catalog: bool = False, target_readback: bool = False,
                        target_preflight: bool = False, target_preflight_readback: bool = False,
                        target_v2: bool = False, source3: bool = False, intourist4: bool = False,
                        intourist4_readback: bool = False, funsun2: bool = False, anex2: bool = False, delta: bool = False, bf8: bool = False, bp8: bool = False, bf5: bool = False, nf7: bool = False, nu5: bool = False, nr5: bool = False, observed_page1: bool = False, passive_oct4: bool = False, alias3: bool = False, alias3_metadata: bool = False, operator115_only2: bool = False, native_absent3: bool = False, saved_urls: bool = False) -> str:
    remote = core.REMOTE
    definition = 'def run_match942(stage, mode, offset, limit):\n'
    dispatch = "    if mode=='match-tv942-write':\n"
    collector = "    if mode not in ('reconcile',"
    core.need(remote.count(definition) == 1 and remote.count(dispatch) == 1
              and remote.count(collector) == 2, 'primary_registration_source_drift')
    handler = REMOTE_PROOF_HANDLER if proof else REMOTE_HANDLER
    mode_dispatch = REMOTE_PROOF_DISPATCH if proof else REMOTE_DISPATCH
    selected_mode = READBACK_MODE if proof else MODE
    if native:
        handler, mode_dispatch, selected_mode = REMOTE_NATIVE_HANDLER, REMOTE_NATIVE_DISPATCH, NATIVE_MODE
    if guarded:
        handler, mode_dispatch, selected_mode = REMOTE_GUARDED_HANDLER, REMOTE_GUARDED_DISPATCH, GUARDED_MODE
    if bg:
        handler, mode_dispatch, selected_mode = REMOTE_BG_HANDLER, REMOTE_BG_DISPATCH, BG_MODE
    if shams_geo:
        handler, mode_dispatch, selected_mode = REMOTE_SHAMS_GEO_HANDLER, REMOTE_SHAMS_GEO_DISPATCH, SHAMS_GEO_MODE
    if shams_geo_readback:
        handler, mode_dispatch, selected_mode = REMOTE_SHAMS_GEO_READBACK_HANDLER, REMOTE_SHAMS_GEO_READBACK_DISPATCH, SHAMS_GEO_READBACK_MODE
    if shams_write:
        handler, mode_dispatch, selected_mode = REMOTE_SHAMS_WRITE_HANDLER, REMOTE_SHAMS_WRITE_DISPATCH, SHAMS_WRITE_MODE
    if target_catalog:
        handler, mode_dispatch, selected_mode = REMOTE_TARGET_HANDLER, REMOTE_TARGET_DISPATCH, TARGET_MODE
    if target_readback:
        handler, mode_dispatch, selected_mode = REMOTE_TARGET_READBACK_HANDLER, REMOTE_TARGET_READBACK_DISPATCH, TARGET_READBACK_MODE
    if target_preflight:
        handler, mode_dispatch, selected_mode = REMOTE_TARGET_PREFLIGHT_HANDLER, REMOTE_TARGET_PREFLIGHT_DISPATCH, TARGET_PREFLIGHT_MODE
    if target_preflight_readback:
        handler, mode_dispatch, selected_mode = REMOTE_TARGET_PREFLIGHT_READBACK_HANDLER, REMOTE_TARGET_PREFLIGHT_READBACK_DISPATCH, TARGET_PREFLIGHT_READBACK_MODE
    if target_v2:
        handler, mode_dispatch, selected_mode = REMOTE_TARGET_V2_HANDLER, REMOTE_TARGET_V2_DISPATCH, TARGET_V2_MODE
    if source3:
        handler, mode_dispatch, selected_mode = REMOTE_SOURCE3_HANDLER, REMOTE_SOURCE3_DISPATCH, SOURCE3_MODE
    if intourist4:
        handler, mode_dispatch, selected_mode = REMOTE_INTOURIST4_HANDLER, REMOTE_INTOURIST4_DISPATCH, INTOURIST4_MODE
    if intourist4_readback:
        handler, mode_dispatch, selected_mode = REMOTE_INTOURIST4_READBACK_HANDLER, REMOTE_INTOURIST4_READBACK_DISPATCH, INTOURIST4_READBACK_MODE
    if funsun2:
        handler, mode_dispatch, selected_mode = REMOTE_FUNSUN2_HANDLER, REMOTE_FUNSUN2_DISPATCH, FUNSUN2_MODE
    if anex2:
        handler, mode_dispatch, selected_mode = REMOTE_ANEX2_HANDLER, REMOTE_ANEX2_DISPATCH, ANEX2_MODE
    if delta:
        handler, mode_dispatch, selected_mode = REMOTE_DELTA_HANDLER, REMOTE_DELTA_DISPATCH, DELTA_MODE
    if bf8:
        handler, mode_dispatch, selected_mode = REMOTE_BF8_HANDLER, REMOTE_BF8_DISPATCH, BF8_MODE
    if bp8:
        handler, mode_dispatch, selected_mode = REMOTE_BP8_HANDLER, REMOTE_BP8_DISPATCH, BP8_MODE
    if bf5:
        handler, mode_dispatch, selected_mode = REMOTE_BF5_HANDLER, REMOTE_BF5_DISPATCH, BF5_MODE
    if nf7:
        handler, mode_dispatch, selected_mode = REMOTE_NF7_HANDLER, REMOTE_NF7_DISPATCH, NF7_MODE
    if nu5:
        handler, mode_dispatch, selected_mode = REMOTE_NU5_HANDLER, REMOTE_NU5_DISPATCH, NU5_MODE
    if nr5:
        handler, mode_dispatch, selected_mode = REMOTE_NR5_HANDLER, REMOTE_NR5_DISPATCH, NR5_MODE
    if observed_page1:
        handler, mode_dispatch, selected_mode = REMOTE_OBSERVED_PAGE1_HANDLER, REMOTE_OBSERVED_PAGE1_DISPATCH, OBSERVED_PAGE1_MODE
    if passive_oct4:
        handler, mode_dispatch, selected_mode = REMOTE_PASSIVE_OCT4_HANDLER, REMOTE_PASSIVE_OCT4_DISPATCH, PASSIVE_OCT4_MODE
    if alias3:
        handler, mode_dispatch, selected_mode = REMOTE_ALIAS3_HANDLER, REMOTE_ALIAS3_DISPATCH, ALIAS3_MODE
    if alias3_metadata:
        handler, mode_dispatch, selected_mode = REMOTE_ALIAS3_METADATA_HANDLER, REMOTE_ALIAS3_DISPATCH, ALIAS3_METADATA_MODE
    if saved_urls:
        handler, mode_dispatch, selected_mode = REMOTE_URL3_HANDLER, REMOTE_URL3_DISPATCH, URL3_MODE
    if native_absent3:
        handler, mode_dispatch, selected_mode = REMOTE_NA3_HANDLER, REMOTE_NA3_DISPATCH, NA3_MODE
    if operator115_only2:
        handler, mode_dispatch, selected_mode = REMOTE_OP1152_HANDLER, REMOTE_OP1152_DISPATCH, OP1152_MODE
    if intourist4 or intourist4_readback or funsun2 or anex2:
        # These fixed Tourvisor operations are authorized by the registered parser.
        # Bind the emitted first guard to the exact triple, before any reservation;
        # do not broaden the stock operation namespace for other modes.
        operation_guard = "    if not re.fullmatch(r'int-(?:anex|andromeda)-[a-z0-9-]{8,80}-v[1-9][0-9]*',operation):\n"
        core.need(remote.count(operation_guard) == 1, 'primary_operation_guard_source_drift')
        selected_operation, selected_batch = (
            (INTOURIST4_OPERATION, INTOURIST4_BATCH) if intourist4 else
            (INTOURIST4_READBACK_OPERATION, INTOURIST4_READBACK_BATCH) if intourist4_readback else
            (FUNSUN2_OPERATION, FUNSUN2_BATCH) if funsun2 else
            (ANEX2_OPERATION, ANEX2_BATCH)
        )
        exact_guard = (f"    if not (mode=={selected_mode!r} and operation=={selected_operation!r} "
                       f"and payload.get('batch')=={selected_batch!r}):\n")
        remote = remote.replace(operation_guard, exact_guard, 1)
    remote = remote.replace(definition, handler + definition, 1)
    remote = remote.replace(dispatch, mode_dispatch + dispatch, 1)
    remote = remote.replace(collector, "    if mode not in ('" + selected_mode + "','reconcile',")
    ast.parse(remote)
    return remote


def activate(core, command: dict) -> None:
    if command.get('mode') not in (MODE, READBACK_MODE, NATIVE_MODE, GUARDED_MODE, BG_MODE, SHAMS_GEO_MODE, SHAMS_GEO_READBACK_MODE, SHAMS_WRITE_MODE, TARGET_MODE, TARGET_READBACK_MODE, TARGET_PREFLIGHT_MODE, TARGET_PREFLIGHT_READBACK_MODE, TARGET_V2_MODE, SOURCE3_MODE, INTOURIST4_MODE, INTOURIST4_READBACK_MODE, FUNSUN2_MODE, ANEX2_MODE, DELTA_MODE, BF8_MODE, BP8_MODE, BF5_MODE, NF7_MODE, NU5_MODE, NR5_MODE, OBSERVED_PAGE1_MODE, PASSIVE_OCT4_MODE, ALIAS3_MODE, ALIAS3_METADATA_MODE, OP1152_MODE, NA3_MODE, URL3_MODE):
        return
    expected = core.parse_command(core.PREFIX + ' '.join([
        str(command.get('source_sha','')), command['mode'],
        str(command.get('operation_id','')), str(command.get('batch','')),
    ]))
    core.need(command == expected, 'primary_authorized_command_shape')
    if command['mode'] in (OBSERVED_PAGE1_MODE, PASSIVE_OCT4_MODE, ALIAS3_MODE, ALIAS3_METADATA_MODE, OP1152_MODE, NA3_MODE, URL3_MODE):
        core.need(type(command.get('maximum_writes')) is int
                  and type(command.get('provider_http_calls')) is int,
                  'observed_page1_authorized_counter_type')
    proof = command['mode'] == READBACK_MODE
    native = command['mode'] == NATIVE_MODE
    guarded = command['mode'] == GUARDED_MODE
    bg = command['mode'] == BG_MODE
    shams_geo = command['mode'] == SHAMS_GEO_MODE
    shams_geo_readback = command['mode'] == SHAMS_GEO_READBACK_MODE
    shams_write = command['mode'] == SHAMS_WRITE_MODE
    target_catalog = command['mode'] == TARGET_MODE
    target_readback = command['mode'] == TARGET_READBACK_MODE
    target_preflight = command['mode'] == TARGET_PREFLIGHT_MODE
    target_preflight_readback = command['mode'] == TARGET_PREFLIGHT_READBACK_MODE
    target_v2 = command['mode'] == TARGET_V2_MODE
    source3 = command['mode'] == SOURCE3_MODE
    intourist4 = command['mode'] == INTOURIST4_MODE
    intourist4_readback = command['mode'] == INTOURIST4_READBACK_MODE
    funsun2 = command['mode'] == FUNSUN2_MODE
    anex2 = command['mode'] == ANEX2_MODE
    delta = command['mode'] == DELTA_MODE
    bp8 = command['mode'] == BP8_MODE
    bf5 = command['mode'] == BF5_MODE
    nf7 = command['mode'] == NF7_MODE
    nu5 = command['mode'] == NU5_MODE
    nr5 = command['mode'] == NR5_MODE
    observed_page1 = command['mode'] == OBSERVED_PAGE1_MODE
    passive_oct4 = command['mode'] == PASSIVE_OCT4_MODE
    alias3 = command['mode'] == ALIAS3_MODE
    alias3_metadata = command['mode'] == ALIAS3_METADATA_MODE
    operator115_only2 = command['mode'] == OP1152_MODE
    saved_urls = command['mode'] == URL3_MODE
    native_absent3 = command['mode'] == NA3_MODE
    bf8 = command['mode'] == BF8_MODE
    remote = remote_with_primary(core, proof, native, guarded, bg, shams_geo, shams_geo_readback, shams_write, target_catalog, target_readback, target_preflight, target_preflight_readback, target_v2, source3, intourist4, intourist4_readback, funsun2, anex2, delta, bf8, bp8, bf5, nf7, nu5, nr5, observed_page1, passive_oct4, alias3, alias3_metadata, operator115_only2, native_absent3, saved_urls)
    if source3 or intourist4 or funsun2 or anex2:
        # activate is reached only after stock checked_event; parse-only exits before it.
        token = os.environ.get('GH_TOKEN', '')
        core.need(bool(token), 'match_supplier_slot_token')
        core.ensure_supplier_slot(token)
    files = list(core.FIXED)
    selected_files = SHAMS_WRITE_SOURCE_FILES if shams_write else (SHAMS_GEO_SOURCE_FILES if (shams_geo or shams_geo_readback) else (BG_SOURCE_FILES if bg else (GUARDED_SOURCE_FILES if guarded else (NATIVE_SOURCE_FILES if native else (PROOF_SOURCE_FILES if proof else SOURCE_FILES)))))
    if target_catalog or target_readback:
        selected_files = TARGET_SOURCE_FILES
    if target_preflight or target_preflight_readback:
        selected_files = TARGET_PREFLIGHT_SOURCE_FILES
    if target_v2:
        selected_files = TARGET_V2_SOURCE_FILES
    if source3:
        selected_files = SOURCE3_SOURCE_FILES
    if intourist4:
        selected_files = INTOURIST4_SOURCE_FILES
    if intourist4_readback:
        selected_files = ()
    if funsun2:
        selected_files = FUNSUN2_SOURCE_FILES
    if anex2:
        selected_files = ANEX2_SOURCE_FILES
    if delta:
        selected_files = DELTA_SOURCE_FILES
    if bf8:
        selected_files = BF8_SOURCE_FILES
    if bp8:
        selected_files = BP8_SOURCE_FILES
    if bf5:
        selected_files = BF5_SOURCE_FILES
    if nf7:
        selected_files = NF7_SOURCE_FILES
    if nu5:
        selected_files = NU5_SOURCE_FILES
    if nr5:
        selected_files = NR5_SOURCE_FILES
    if observed_page1:
        selected_files = OBSERVED_PAGE1_SOURCE_FILES
    if passive_oct4:
        selected_files = PASSIVE_OCT4_SOURCE_FILES
    if alias3 or alias3_metadata:
        selected_files = ALIAS3_SOURCE_FILES
    if saved_urls:
        selected_files = URL3_SOURCE_FILES
    if native_absent3:
        selected_files = NA3_SOURCE_FILES
    if operator115_only2:
        selected_files = OP1152_SOURCE_FILES
    for path in selected_files:
        if path not in files:
            files.append(path)
    core.FIXED = files
    core.REMOTE = remote
