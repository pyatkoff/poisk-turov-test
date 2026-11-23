"""Reviewed retained-evidence intake in the stock carrier; no new SSH/auth route."""
from __future__ import annotations
import ast
import copy
import datetime as dt
import hashlib
import io
import inspect
import json
import os
from pathlib import Path
import re
import tarfile

MODES = {'match-retained-intake': 'common4-retained',
         'match-tourvisor-quota-policy': 'tourvisor-anex-5000',
         'match-retained-readback': 'common4-retained',
         'match-retained-project': 'common4-retained',
         'match-retained-current': 'common4-retained',
         'match-retained-acquire': 'anex105-current',
         'match-retained-anex-write': 'anex7-verified',
         'match-retained-anex-write-v2': 'anex7-reconciled'}
# The legacy remote dictionary is byte-frozen. The explicit LOCAL9 write mode
# is registered with the existing carrier without changing any read profile.
LEGACY_MODES = dict(MODES)
LOCAL9_APPLY_MODE = 'local9-bridge-apply-v1'
MODES[LOCAL9_APPLY_MODE] = 'local9-bridge-manual27-20261123'
LOCAL19_APPLY_MODE = 'local19-bridge-apply-v1'
MODES[LOCAL19_APPLY_MODE] = 'local19-bridge-manual57-20261123'
# Historical accepted pairs from the owner's ANEX roster. This current read
# grants no website/Online equivalence, canonical publication binding or write.
OWNER_TOP500_CURRENT = {
    'cohort': 'owner-top500-retained7-20261106',
    'operation_id': 'int-andromeda-match-retained-current-20261106-v1',
    'parent_operation_id': 'int-andromeda-match-retained-project-20261020-v2',
    'parent_receipt_sha256': '4b7c04b3934def5554017a507310dad56d110e996043ce4a6bc03ec561c6b6b1',
    'pairs': ((27611,1228,8),(8554,16794,9),(8595,1216,108),
              (8476,1095,150),(8475,1014,159),(12901,1474,190),(14818,1015,209)),
}

SOURCE_FILES = ('scripts/diagnostics/hotel_match_retained_intake_v1.php', 'scripts/diagnostics/hotel_match_retained_projection_v1.py', 'scripts/diagnostics/hotel_match_tourvisor_quota_v1.py', 'v2/data/db-v1.php', 'scripts/diagnostics/hotel_match_retained_current_v1.php', 'scripts/diagnostics/hotel_match_anex_effective_coverage.php', 'scripts/diagnostics/hotel_match_live30_common4_anex_current_v4.php', 'app/integrations/anex-search-mapping-registry.php', 'scripts/diagnostics/hotel_match_retained_acquire_v1.py', 'scripts/diagnostics/hotel_match_live30_common4_continuation_acquire_v10.py', 'scripts/diagnostics/hotel_match_retained_anex_write_v1.php', 'scripts/diagnostics/hotel_match_live30_common4_anex_writer_v5.php', 'scripts/diagnostics/fixtures/hotel_match_retained_next_scope_20261020.json', 'scripts/diagnostics/fixtures/hotel_match_retained_timeout60_scope_20261020.json', 'scripts/diagnostics/fixtures/hotel_match_retained_wide_scope_20261020.json', 'scripts/diagnostics/fixtures/hotel_match_retained_never13_scope_20261020.json', 'scripts/diagnostics/fixtures/hotel_match_retained_never9_scope_20261020.json')
SOURCE_HASHES = {'scripts/diagnostics/hotel_match_retained_intake_v1.php': '8c504844d96632e7bcd13d3becf16a7b7e5b388a64c2ef11e8c24f23a25a52c1', 'scripts/diagnostics/hotel_match_retained_projection_v1.py': '6ad86cdcd33edce8d20d238fdd4ba46dc61b76b16e6b32a85028fb2853a000d4', 'scripts/diagnostics/hotel_match_tourvisor_quota_v1.py': '74a9735375fd40bb776c2d8e6f4af15fc477dd88dc6b879e18436e90ad5b4df5', 'v2/data/db-v1.php': 'c1c841d38c62845bc36e559183da17e0411c1c5bdd909517ffc02b5fbb1e2f13', 'scripts/diagnostics/hotel_match_retained_current_v1.php': 'fc0c7f1dc6d913dd83ac7713ba1572ca3f17921da16d11b57e246c289e3615e2', 'scripts/diagnostics/hotel_match_anex_effective_coverage.php': '638694ec2d7254d4436f0cdac3f7ce780ef3c987d12ecf0e648c9ad64e2f4067', 'scripts/diagnostics/hotel_match_live30_common4_anex_current_v4.php': '98243ba62910a2becf3c15a6a5c73d9635d07bca84374110d377577680a906b3', 'app/integrations/anex-search-mapping-registry.php': 'ba06d082ac66d671efc01569a7d11e9d8b5abd73cabde8ea72ecc154b3e67814', 'scripts/diagnostics/hotel_match_retained_acquire_v1.py': '6b52756ce4417d9489d9c3ce9d94b0f250485c324e00e6eade566564e00caace', 'scripts/diagnostics/hotel_match_live30_common4_continuation_acquire_v10.py': '9690f85d2d7e5c5e66f946e83024c1df98ab7f4e35285e9b0cbd8acde72f385f', 'scripts/diagnostics/hotel_match_retained_anex_write_v1.php': '0156d1cad6a1f16d6eff5b5587993fafb61d8ad2854e5da3ef4f26b998aa8e06', 'scripts/diagnostics/hotel_match_live30_common4_anex_writer_v5.php': '4c6f232d139b2d88727be17727a072acefcb59b9a1aec5086b95c085318a04d4', 'scripts/diagnostics/fixtures/hotel_match_retained_next_scope_20261020.json': '458857b2e3641c143699247ea7c92c235cd9a0765b3e06fbce4aa81adfe41c31', 'scripts/diagnostics/fixtures/hotel_match_retained_timeout60_scope_20261020.json': '266f0791573661882104a39ec1b4206940cec16eefef29de4d2e0a11f52ede07', 'scripts/diagnostics/fixtures/hotel_match_retained_wide_scope_20261020.json': 'c83d38a5726d71e955743e133dd815e9898a9a3067ba6048c5e1c31076ee6379', 'scripts/diagnostics/fixtures/hotel_match_retained_never13_scope_20261020.json': '6886821dd545f24e1a3f35db2c3f9b87ede3597df9e73fd6aaae190c0f99dce5', 'scripts/diagnostics/fixtures/hotel_match_retained_never9_scope_20261020.json': '175e28a9cd21dc15ec9aed34d6acb0444370374116c3fbe38062715b519958b3'}


NEXT_SCOPE = {'cohort': 'anex-next-ee68d11668a22e84', 'operation_id': 'int-andromeda-match-retained-acquire-20261020-v4', 'write_operation_id': 'int-andromeda-match-retained-anex-write-20261020-v5', 'readback_operation_id': 'int-andromeda-match-retained-readback-20261020-v3', 'saved_acquisition_operation_id': 'int-andromeda-match-retained-acquire-20261020-v2', 'parent_operation_id': 'int-andromeda-match-retained-current-20261020-v2', 'parent_evidence_sha256': 'fbdbc3c0e5327d77017ad2b65599d841849ab332e900c2883a5a5d7dc2578c57', 'scope_id': 'ee68d11668a22e84934fa4acd94a779238491f8a0db822ee2f91dc2212456f04', 'selected_count': 25, 'path': 'scripts/diagnostics/fixtures/hotel_match_retained_next_scope_20261020.json', 'sha256': '458857b2e3641c143699247ea7c92c235cd9a0765b3e06fbce4aa81adfe41c31'}

NEXT_60_SCOPE = {'cohort': 'anex-next-358bc9fca19be2f8', 'operation_id': 'int-andromeda-match-retained-acquire-20261020-v5', 'write_operation_id': 'int-andromeda-match-retained-anex-write-20261020-v6', 'readback_operation_id': 'int-andromeda-match-retained-readback-20261020-v3', 'saved_acquisition_operation_id': 'int-andromeda-match-retained-acquire-20261020-v2', 'parent_operation_id': 'int-andromeda-match-retained-current-20261020-v2', 'parent_evidence_sha256': 'fbdbc3c0e5327d77017ad2b65599d841849ab332e900c2883a5a5d7dc2578c57', 'scope_id': '358bc9fca19be2f882660a21d2c6b99ac1048bd4bb83a2d21327e6d1197cecaa', 'selected_count': 25, 'path': 'scripts/diagnostics/fixtures/hotel_match_retained_timeout60_scope_20261020.json', 'sha256': '266f0791573661882104a39ec1b4206940cec16eefef29de4d2e0a11f52ede07'}

NEXT_WIDE_SCOPE = {'cohort': 'anex-next-a286c36432d9174f', 'operation_id': 'int-andromeda-match-retained-acquire-20261020-v6', 'write_operation_id': 'int-andromeda-match-retained-anex-write-20261020-v7', 'readback_operation_id': 'int-andromeda-match-retained-readback-20261020-v3', 'saved_acquisition_operation_id': 'int-andromeda-match-retained-acquire-20261020-v2', 'parent_operation_id': 'int-andromeda-match-retained-current-20261020-v2', 'parent_evidence_sha256': 'fbdbc3c0e5327d77017ad2b65599d841849ab332e900c2883a5a5d7dc2578c57', 'scope_id': 'a286c36432d9174f1989561ca4bfc4b05f98ecf1ea61ef2b48b68f4b771f73c3', 'selected_count': 25, 'path': 'scripts/diagnostics/fixtures/hotel_match_retained_wide_scope_20261020.json', 'sha256': 'c83d38a5726d71e955743e133dd815e9898a9a3067ba6048c5e1c31076ee6379'}
NEXT_NEVER13_SCOPE = {'cohort':'anex-next-e6f47f44a76c11fc','operation_id':'int-andromeda-match-retained-acquire-20261020-v7','write_operation_id':'int-andromeda-match-retained-anex-write-20261020-v8','parent_operation_id':'int-andromeda-match-retained-current-20261020-v2','parent_evidence_sha256':'fbdbc3c0e5327d77017ad2b65599d841849ab332e900c2883a5a5d7dc2578c57','scope_id':'e6f47f44a76c11fc6725efc36143717964c2178a3007178e1df22170a38906c2','selected_count':13,'path':'scripts/diagnostics/fixtures/hotel_match_retained_never13_scope_20261020.json','sha256':'6886821dd545f24e1a3f35db2c3f9b87ede3597df9e73fd6aaae190c0f99dce5','scope_kind':'never_selected_bounded','context_origin':'saved_user_search_exact_date_nights','wire_context':{'date_from':'2026-11-11','date_to':'2026-11-11','nights_from':9,'nights_to':9},'supplier_http_maximum':30,'search_start_maximum':1,'http_timeout_seconds':60}
NEXT_NEVER9_SCOPE = {"cohort":"anex-next-fcb0ccc19be1929c","operation_id":"int-andromeda-match-retained-acquire-20261020-v8","write_operation_id":"int-andromeda-match-retained-anex-write-20261020-v9","parent_operation_id":"int-andromeda-match-retained-current-20261020-v2","parent_evidence_sha256":"fbdbc3c0e5327d77017ad2b65599d841849ab332e900c2883a5a5d7dc2578c57","scope_id":"fcb0ccc19be1929cbed8608244f1b47d853525f73c215c5c4588f85b8d95529f","selected_count":9,"scope_kind":"never_selected_bounded","context_origin":"saved_user_search_exact_date_nights","wire_context":{"date_from":"2026-10-21","date_to":"2026-10-21","nights_from":5,"nights_to":5},"supplier_http_maximum":30,"search_start_maximum":1,"http_timeout_seconds":60,"country_id":8,"departure_id":1,"adults":2,"children_count":0,"path":"scripts/diagnostics/fixtures/hotel_match_retained_never9_scope_20261020.json","sha256":"175e28a9cd21dc15ec9aed34d6acb0444370374116c3fbe38062715b519958b3"}
CONTINUATION_SCOPES = {p['cohort']:p for p in (NEXT_SCOPE,NEXT_60_SCOPE,NEXT_WIDE_SCOPE,NEXT_NEVER13_SCOPE,NEXT_NEVER9_SCOPE)}

SAVED_DETAIL_READBACKS = {'int-andromeda-match-retained-readback-20261020-v7': {
    'cohort': NEXT_NEVER13_SCOPE['cohort'], 'target_operation_id': NEXT_NEVER13_SCOPE['operation_id'],
    'source_sha': '45085a38b10edaecc6f1dcfef5d2e118dd753fda',
    'receipt_sha256': 'e15bb5c7fb00090a25d842c473601c0e8f44abb9e6f180721e579e194d58be53',
    'evidence_sha256': 'b5aacc5feee4854c3ad48c152d19bfe0e9e7309933ddfcab1a47b3eecd4ab34f',
    'tv_hotel_id': 616, 'response_number': 4,
    'raw_sha256': 'ba75482de581f48d90aa2a14c5a61616438b1600a0dd9b30d5674dbea4c01752',
    'hotel_ledger_sha256': '60d411fe1d1079d5a718cabc27ca31cc973c55f6ec368eb2bd879803013c3ffa',
    'context_ledger_sha256': '054f5977db0ba9810787ab8031b15a6c8005c6e52c3a1152c11cec0ceab2614a'}}

TRANSPORT_DIAGNOSTIC = {
    'operation_id': 'int-andromeda-match-retained-readback-20261020-v6',
    'target_operation_id': 'int-andromeda-match-retained-readback-20261020-v5',
    'failed_receipt_sha256': '1f74aa9e3f900d06c2277fc95683893d13ad95e38de3c59121a547caf066e305',
    'failed_source_sha': '78f029da7522bc97051904770e7c077bbe1b985e',
    'parents': [
        {'version': 1, 'calls': 38, 'unknown': 0, 'evidence': 'cb7f28c82d5df5b8f069f894ab978f198b2eebfc949034c8e371050c4f4e0aeb'},
        {'version': 2, 'calls': 7, 'unknown': 1, 'evidence': 'cd58ab7c155c28688de65b43c196cbc8c6ddf406c76e92c890460af6b712b2f8'},
        {'version': 3, 'calls': 1, 'unknown': 1, 'evidence': '91a307969dedc5f160acb1ebf088e450f7d40299f9f0981dc5cc69fe9f3ed23f'},
        {'version': 4, 'calls': 1, 'unknown': 1, 'evidence': '41c2949ab853ce88dc8509e20e18135e45cd05770ebcb1ae48ae005c094fbc9c'},
    ],
}

PROOF_SOURCE_INSPECTION = {
    'operation_id':'int-andromeda-match-retained-readback-20261020-v8',
    'target_operation_id':'int-andromeda-match-retained-20261020-v3',
    'cohort':'common4-retained',
    'capture_receipt_sha256':'b610629916e80d706edb493da1fc02eb0bf38a00de64243a1973bb80155485d6',
    'capture_raw_sha256':'00c992ec930b0f0c8c9a8167bea93a1efebd8d34e1c19f1a01bf16e3ef77d834',
    'capture_raw_bytes':62108585,
    'current_operation_id':'int-andromeda-match-retained-current-20261020-v2',
    'current_receipt_sha256':'627aaba39d6ec77a2e0e986d524324cc262cf8fea4a50b1fe5203c0ecf6da7c9',
    'current_evidence_sha256':'fbdbc3c0e5327d77017ad2b65599d841849ab332e900c2883a5a5d7dc2578c57',
    'acquisition_operation_id':'int-andromeda-match-retained-acquire-20261020-v9',
    'acquisition_source_sha':'ba6ecf2ae842bea1424a585b5a2eb20907ffba9a',
    'acquisition_receipt_sha256':'1cf755d5aa043d92ee6780bf0d7b0117b330ab41beb6fff23ab5c3d0dd2c544a',
    'acquisition_evidence_sha256':'4725428bd44d20704c3f274b09a43d8de21837053e3d959751510bbdb5482324',
    'catalogue_sha256':'bf61e5bcc50cfa6264819fca384915c3e4a45f756e304a96a22bc65ad2120b73',
    'hotel_ledger_sha256':'d1a0e674c7110661071095cca50c6de5b95996c0a2bcb81d936f70dd6880caea',
    'context_ledger_sha256':'054f5977db0ba9810787ab8031b15a6c8005c6e52c3a1152c11cec0ceab2614a',
    'remaining_id_sha256':'6f68c592411632a37e462be2dbaea20fc60e4badd7f25e76cf7a7f581e0989d1',
    'selected_id_sha256':'fbf10fd2008bb9b69b633ada6b3e4dc93f96e329e85294a7516258874e9eb8a7',
}


OPERATOR_BINDING = {
    'cohort': 'common4-egypt-operator-binding-20261021',
    'operation_id': 'int-andromeda-match-retained-acquire-20261021-v1',
    'parent_operation_id': 'int-andromeda-match-retained-current-20261020-v2',
    'parent_evidence_sha256': 'fbdbc3c0e5327d77017ad2b65599d841849ab332e900c2883a5a5d7dc2578c57',
}

BINDING_RECONCILIATION = {
    'cohort': 'common4-binding-failure-20261021',
    'operation_id': 'int-andromeda-match-retained-readback-20261021-v1',
    'parent_operation_id': OPERATOR_BINDING['operation_id'],
    'failed_source_sha': 'a74f6405db37214cb64edd2c6695024921877ac4',
    'failed_canonical_sha256': '6d48def9061bf57966f5f2d48e8aa1d76c544161b3b6a833c3b18d671ff0a858',
    'provider_day': '2026-10-21',
}

BINDING_RECONCILIATION_V2 = dict(BINDING_RECONCILIATION,
    cohort='common4-binding-two-failures-20261021',
    operation_id='int-andromeda-match-retained-readback-20261021-v2',
    prior_reconciliation_id='int-andromeda-match-retained-readback-20261021-v1',
    prior_reconciliation_source='2f12d0f45c0ce5adad2475cb48fb38ffa64bdc44',
    prior_reconciliation_digest='e4d082c58a7e9bf6fb61abf01372453989b629ab13855b8b7fc30c2f91dc0834')

OPERATOR_BINDING_FULL = {
    'cohort': 'common4-full-operator-binding-20261022',
    'operation_id': 'int-andromeda-match-retained-acquire-20261022-v1',
    'parent_operation_id': BINDING_RECONCILIATION_V2['operation_id'],
    'parent_evidence_sha256': 'ea17686a3fb7c1a47fc349e3333f1fb24729c5f1a77bb2b6e6f3f27a6a707a10',
}

BG_IDENTITY_SCOPE = {
    'cohort': 'biblio-menaville-family13-20261022',
    'operation_id': 'int-andromeda-match-retained-acquire-20261022-v2',
    'parent_operation_id': OPERATOR_BINDING_FULL['operation_id'],
    'parent_evidence_sha256': 'ca613f2afe8ba344fdf57f1232ad174a42aea430ab44bbffa4a037b67a9f0291',
    'current_operation_id': 'int-andromeda-match-retained-current-20261020-v2',
    'current_receipt_sha256': '627aaba39d6ec77a2e0e986d524324cc262cf8fea4a50b1fe5203c0ecf6da7c9',
    'current_evidence_sha256': 'fbdbc3c0e5327d77017ad2b65599d841849ab332e900c2883a5a5d7dc2578c57',
    'target_row_sha256': '1af1b88b0bfec19efe97c2575fb75a9f9dda3f4addf000cb68b517d402dbdc06',
    'operator_id': 18, 'tv_hotel_id': 321,
    'wire': {'departureId': 11, 'countryId': 1, 'dateFrom': '2026-10-24', 'dateTo': '2026-10-24',
             'nightsFrom': 6, 'nightsTo': 6, 'adults': 1, 'childs': [13],
             'currency': 'RUB', 'onlyCharter': False, 'operatorIds': [18], 'hotelIds': [321]},
}

OPERATOR_ALIAS_AUDIT = {
    'cohort': 'common4-funsun-dictionary-alias-20261022',
    'operation_id': 'int-andromeda-match-retained-readback-20261022-v1',
    'parent_operation_id': OPERATOR_BINDING_FULL['operation_id'],
    'parent_evidence_sha256': BG_IDENTITY_SCOPE['parent_evidence_sha256'],
    'captured_source_sha': '6cdf8ae1720d6336e677eabc13659b0b8ce1cd60',
    'raw_sha256': '3f915ea52e17f12eb036a2dd4835418d78c0b387551b689884b593b3d18bd905',
}
_ALIAS_BRAND = r'(?:FUN[ ]*&[ ]*SUN|FUN[ ]+AND[ ]+SUN|ФАН[ ]*&[ ]*САН|TUI|ТУИ)'
OPERATOR_ALIAS_LABEL_PATTERN = _ALIAS_BRAND + r'(?:[ ]*(?:[/\-][ ]*(?:ex[.]?[ ]+)?' + _ALIAS_BRAND + r'|\((?:ex[.]?[ ]+)?' + _ALIAS_BRAND + r'\)))?'

# Exact retained UTF-8 label digest, corroborated by Tourvisor's public operator
# list on 2026-10-22. RU, Asia and BY remain distinct dictionary identities.
FUNSUN_RU_IDENTITY_SCOPE = dict(BG_IDENTITY_SCOPE,
    cohort='funsun-ru-menaville-family13-20261022',
    operation_id='int-andromeda-match-retained-acquire-20261022-v3',
    parent_operation_id=OPERATOR_ALIAS_AUDIT['operation_id'],
    parent_evidence_sha256='6fb349c8aa40eeebfd9d623b04116cd5973581258a6b5bec628c1dc458d4d649',
    parent_receipt_sha256='7fe2275b620e28c76461cb2aa9f1adedf637987f52d0457bfff5803e05b3b32b',
    alias_source_sha='5a703baff21087e963f2eb40b901a452e5e0e0a9',
    alias_literal='Fun&Sun (RU)',
    alias_literal_sha256='c45412aa51aa4affa0e3d3cd8c3e65ff73ce74612e921c9e1052d3edf178907b',
    operator_id=25,
    wire=dict(BG_IDENTITY_SCOPE['wire'], operatorIds=[25]))


def need(value, reason):
    if not value:
        raise ValueError(reason)


CATALOGUE_PATH = 'scripts/deploy/fixtures/hotel_match_retained_scope_catalogue_v1.json'
CATALOGUE_BASELINE_SHA256 = 'dd57f6cb72ec68f97a605bbb9fbf940c91f306e7ba7e4010e00da6e15a924434'
_CATALOGUE_SNAPSHOT = None


def canonical(value):
    return json.dumps(value, sort_keys=True, separators=(',', ':')).encode()


def validate_catalogue(raw):
    """Finite trusted-main data, never a roster supplied by a command."""
    def unique(pairs):
        out = {}
        for key, value in pairs:
            need(key not in out, 'catalogue_duplicate_key')
            out[key] = value
        return out
    need(0 < len(raw) <= 1024*1024, 'catalogue_size')
    value = json.loads(raw, object_pairs_hook=unique)
    need(isinstance(value, dict) and set(value) == {'schema_version', 'kind', 'scopes'}
         and type(value['schema_version']) is int and value['schema_version'] == 1
         and value['kind'] == 'retained_never_selected_catalogue'
         and isinstance(value['scopes'], list) and 1 <= len(value['scopes']) <= 100, 'catalogue_shape')
    baseline_keys = ('prior_selected_hotel_ids', 'exclusion_history', 'context_history',
                     'parent_operation_id', 'parent_evidence_sha256', 'parent_receipt_sha256')
    forbidden = set(CONTINUATION_SCOPES)
    operations = {p[k] for p in CONTINUATION_SCOPES.values() for k in ('operation_id', 'write_operation_id')}
    operations.update(SAVED_DETAIL_READBACKS)
    selected = set(); policies = {}
    for scope in value['scopes']:
        need(isinstance(scope, dict) and all(k in scope for k in baseline_keys), 'catalogue_member')
        allowed = {'account','adults','child_ages_signature','children_count','cohort','context_grouping',
                   'context_history','context_origin','context_reason','country_id','current_row_sha256',
                   'database_writes','departure_id','exclusion_history','future_contexts','http_timeout_seconds',
                   'mapping_writes','operation_id','operator_id','owner_authorized_at','owner_confirmation_at',
                   'parent_evidence_sha256','parent_operation_id','parent_receipt_sha256',
                   'prior_acquisition_evidence_sha256','prior_acquisition_operation_id','prior_selected_hotel_ids',
                   'safe_to_write_now','schema_version','scope_id','scope_kind','search_start_maximum',
                   'selected_count','selected_tv_hotel_ids','supplier_http_maximum','wire_context','write_operation_id'}
        need(set(scope)==allowed, 'catalogue_member_fields')
        need(hashlib.sha256(canonical({k:scope[k] for k in baseline_keys})).hexdigest()
             == CATALOGUE_BASELINE_SHA256, 'catalogue_baseline')
        operations.update(x['operation_id'] for x in scope['exclusion_history']+scope['context_history'])
        operations.update(('int-andromeda-match-retained-anex-write-20261020-v1','int-andromeda-match-retained-anex-write-20261020-v2'))
        ids = scope.get('selected_tv_hotel_ids'); wire = scope.get('wire_context')
        need(isinstance(ids, list) and 1 <= len(ids) <= 25
             and all(type(h) is int and h > 0 for h in ids) and ids == sorted(set(ids))
             and not selected.intersection(ids) and not set(ids).intersection(scope['prior_selected_hotel_ids'])
             and type(scope.get('selected_count')) is int and scope['selected_count'] == len(ids), 'catalogue_roster')
        need(type(scope.get('schema_version')) is int and scope['schema_version'] == 1
             and scope.get('account') == 'TOURVISOR_ANEX_JWT' and type(scope.get('operator_id')) is int and scope['operator_id'] == 13
             and scope.get('scope_kind') == 'never_selected_bounded'
             and scope.get('context_origin') == 'saved_user_search_exact_date_nights'
             and scope.get('context_grouping') == 'exact_date_nights'
             and all(type(scope.get(k)) is int and scope[k] == v for k,v in
                     (('supplier_http_maximum',30),('search_start_maximum',1),('http_timeout_seconds',60),
                      ('children_count',0),('database_writes',0),('mapping_writes',0)))
             and scope.get('child_ages_signature') == '' and scope.get('safe_to_write_now') is False
             and all(type(scope.get(k)) is int and scope[k] > 0 for k in ('country_id','departure_id','adults'))
             and scope['adults'] <= 6 and 'readback_operation_id' not in scope, 'catalogue_bounds')
        need(isinstance(wire,dict) and set(wire)=={'date_from','date_to','nights_from','nights_to'}
             and isinstance(wire['date_from'],str) and wire['date_from']==wire['date_to']
             and dt.date.fromisoformat(wire['date_from']).isoformat()==wire['date_from']
             and type(wire['nights_from']) is int and type(wire['nights_to']) is int
             and 1 <= wire['nights_from'] == wire['nights_to'] <= 28, 'catalogue_context')
        identity_keys = ('parent_evidence_sha256','selected_tv_hotel_ids','future_contexts','scope_kind','wire_context','context_origin')
        need(all(k in scope for k in identity_keys), 'catalogue_identity')
        identity = hashlib.sha256(canonical({k:scope[k] for k in identity_keys})).hexdigest()
        cohort = scope.get('cohort')
        need(scope.get('scope_id') == identity and cohort == 'anex-next-'+identity[:16]
             and cohort not in forbidden and cohort not in policies, 'catalogue_identity')
        need(isinstance(scope.get('current_row_sha256'),dict) and set(scope['current_row_sha256'])=={str(h) for h in ids}
             and all(re.fullmatch(r'[a-f0-9]{64}',str(v)) for v in scope['current_row_sha256'].values())
             and isinstance(scope.get('future_contexts'),dict) and set(scope['future_contexts'])=={str(h) for h in ids}, 'catalogue_row_binding')
        for hid in ids:
            ctx = scope['future_contexts'][str(hid)]
            need(isinstance(ctx,dict) and ctx.get('source')=='user_search' and type(ctx.get('hotel_id')) is int and ctx['hotel_id']==hid
                 and all(type(ctx.get(k)) is int and ctx[k]==scope[k] for k in ('country_id','departure_id','adults','children_count'))
                 and ctx.get('child_ages_signature')=='' and ctx.get('departure_date')==wire['date_from']
                 and type(ctx.get('nights')) is int and ctx['nights']==wire['nights_from'], 'catalogue_row_context')
        for key, family in (('operation_id','acquire'),('write_operation_id','anex-write')):
            name = scope.get(key)
            need(isinstance(name,str) and re.fullmatch(r'int-andromeda-match-retained-'+family+r'-[0-9]{8}-v[1-9][0-9]*',name)
                 and name not in operations, 'catalogue_operation')
            operations.add(name)
        selected.update(ids)
        policies[cohort] = dict(copy.deepcopy(scope), path=CATALOGUE_PATH,
                               sha256=hashlib.sha256(canonical(scope)).hexdigest(),
                               catalogue_sha256=hashlib.sha256(raw).hexdigest())
    return policies


def catalogue_snapshot():
    global _CATALOGUE_SNAPSHOT
    path = Path(__file__).resolve().parents[2]/CATALOGUE_PATH
    need(path.is_file() and not path.is_symlink() and path.resolve()==path
         and path.stat().st_nlink==1 and 0 < path.stat().st_size <= 1024*1024, 'catalogue_path')
    raw = path.read_bytes()
    if _CATALOGUE_SNAPSHOT is None:
        _CATALOGUE_SNAPSHOT = (raw, validate_catalogue(raw))
    need(raw == _CATALOGUE_SNAPSHOT[0], 'catalogue_changed_after_parse')
    return raw, copy.deepcopy(_CATALOGUE_SNAPSHOT[1])


def continuation_policy(cohort):
    if cohort in CONTINUATION_SCOPES:
        return CONTINUATION_SCOPES[cohort]
    if cohort in MODES.values() or cohort == OWNER_TOP500_CURRENT['cohort']:
        return NEXT_SCOPE
    return catalogue_snapshot()[1].get(cohort,NEXT_SCOPE)


def register_parser(core):
    original = core.parse_command
    def parse(body):
        if not body.startswith(core.PREFIX):
            return original(body)
        parts = body[len(core.PREFIX):].split()
        if ((len(parts) >= 3 and parts[2] == LOCAL7_REGISTRATION['operation_id'])
                or (len(parts) >= 4 and parts[3] == LOCAL7_REGISTRATION['cohort'])):
            profile = LOCAL7_REGISTRATION
            need(len(parts) == 5 and parts[1] == profile['mode']
                 and parts[0] == profile['source_sha'] and parts[2] == profile['operation_id']
                 and parts[3] == profile['cohort'] and parts[4] == profile['parent_operation_id'],
                 'local7_fixed_read_scope')
            need(os.environ.get('GITHUB_RUN_ATTEMPT', '1') == '1', 'retained_first_attempt_only')
            return dict(source_sha=parts[0], mode=parts[1], operation_id=parts[2],
                        cohort=parts[3], target_operation_id=parts[4])
        if len(parts) < 2 or parts[1] not in MODES:
            return original(body)
        if parts[1] == LOCAL19_APPLY_MODE or (len(parts) >= 3 and parts[2] == LOCAL19_APPLY['operation_id']) or (len(parts) >= 4 and parts[3] == LOCAL19_APPLY['cohort']):
            profile = LOCAL19_APPLY
            need(len(parts) == 5 and parts[1] == LOCAL19_APPLY_MODE
                 and parts[0] == profile['source_sha'] and parts[2] == profile['operation_id']
                 and parts[3] == profile['cohort'] and parts[4] == profile['parent_operation_id'],
                 'local19_fixed_write_scope')
            l19_admission(profile)
            need(os.environ.get('GITHUB_RUN_ATTEMPT', '1') == '1', 'retained_first_attempt_only')
            return dict(source_sha=parts[0], mode=parts[1], operation_id=parts[2],
                        cohort=parts[3], target_operation_id=parts[4])
        if len(parts) >= 3 and (parts[2] == LOCAL26_IDENTITY['operation_id']
                or (len(parts) >= 4 and parts[3] == LOCAL26_IDENTITY['cohort'])):
            profile = LOCAL26_IDENTITY
            need(len(parts) == 5 and parts[1] == 'match-retained-current'
                 and parts[0] == profile['source_sha'] and parts[2] == profile['operation_id']
                 and parts[3] == profile['cohort'] and parts[4] == profile['parent_operation_id'],
                 'local26_fixed_read_scope')
            need(os.environ.get('GITHUB_RUN_ATTEMPT', '1') == '1', 'retained_first_attempt_only')
            return dict(source_sha=parts[0], mode=parts[1], operation_id=parts[2],
                        cohort=parts[3], target_operation_id=parts[4])
        if parts[1] == LOCAL9_APPLY_MODE or (len(parts) >= 3 and parts[2] == LOCAL9_APPLY['operation_id']) or (len(parts) >= 4 and parts[3] == LOCAL9_APPLY['cohort']):
            profile = LOCAL9_APPLY
            need(len(parts) == 5 and parts[1] == LOCAL9_APPLY_MODE
                 and parts[0] == profile['source_sha'] and parts[2] == profile['operation_id']
                 and parts[3] == profile['cohort'] and parts[4] == profile['parent_operation_id'],
                 'local9_fixed_write_scope')
            need(os.environ.get('GITHUB_RUN_ATTEMPT', '1') == '1', 'retained_first_attempt_only')
            return dict(source_sha=parts[0], mode=parts[1], operation_id=parts[2],
                        cohort=parts[3], target_operation_id=parts[4])
        if len(parts) >= 3 and (parts[2] == LOCAL11_IDENTITY['operation_id']
                or (len(parts) >= 4 and parts[3] == LOCAL11_IDENTITY['cohort'])):
            profile = LOCAL11_IDENTITY
            need(len(parts) == 5 and parts[1] == 'match-retained-current'
                 and parts[2] == profile['operation_id'] and parts[3] == profile['cohort']
                 and parts[4] == profile['parent_operation_id'], 'local11_fixed_scope')
            need(core.SHA_RE.fullmatch(parts[0]) is not None, 'retained_source_sha')
            need(os.environ.get('GITHUB_RUN_ATTEMPT', '1') == '1', 'retained_first_attempt_only')
            return dict(source_sha=parts[0], mode=parts[1], operation_id=parts[2],
                        cohort=parts[3], target_operation_id=parts[4])
        if len(parts) >= 4 and (parts[3] == OWNER_TOP500_CURRENT['cohort']
                                or parts[2] == OWNER_TOP500_CURRENT['operation_id']):
            profile = OWNER_TOP500_CURRENT
            need(len(parts) == 5 and parts[1] == 'match-retained-current'
                 and parts[2] == profile['operation_id'] and parts[3] == profile['cohort']
                 and parts[4] == profile['parent_operation_id'], 'owner_top500_current_scope')
            need(core.SHA_RE.fullmatch(parts[0]) is not None, 'retained_source_sha')
            need(os.environ.get('GITHUB_RUN_ATTEMPT', '1') == '1', 'retained_first_attempt_only')
            return dict(source_sha=parts[0], mode=parts[1], operation_id=parts[2],
                        cohort=parts[3], target_operation_id=parts[4])
        if len(parts) >= 4 and parts[3] in (BINDING_RECONCILIATION['cohort'], BINDING_RECONCILIATION_V2['cohort'], OPERATOR_ALIAS_AUDIT['cohort']):
            profile = OPERATOR_ALIAS_AUDIT if parts[3] == OPERATOR_ALIAS_AUDIT['cohort'] else BINDING_RECONCILIATION_V2 if parts[3] == BINDING_RECONCILIATION_V2['cohort'] else BINDING_RECONCILIATION
            need(len(parts) == 5 and parts[1] == 'match-retained-readback'
                 and parts[2] == profile['operation_id']
                 and parts[4] == profile['parent_operation_id'], 'binding_reconciliation_scope')
            need(core.SHA_RE.fullmatch(parts[0]) is not None, 'retained_source_sha')
            need(os.environ.get('GITHUB_RUN_ATTEMPT', '1') == '1', 'retained_first_attempt_only')
            return dict(source_sha=parts[0], mode=parts[1], operation_id=parts[2],
                        cohort=parts[3], target_operation_id=parts[4])
        if len(parts) >= 4 and parts[3] in (OPERATOR_BINDING['cohort'], OPERATOR_BINDING_FULL['cohort'], BG_IDENTITY_SCOPE['cohort'], FUNSUN_RU_IDENTITY_SCOPE['cohort']):
            profile = FUNSUN_RU_IDENTITY_SCOPE if parts[3] == FUNSUN_RU_IDENTITY_SCOPE['cohort'] else BG_IDENTITY_SCOPE if parts[3] == BG_IDENTITY_SCOPE['cohort'] else OPERATOR_BINDING_FULL if parts[3] == OPERATOR_BINDING_FULL['cohort'] else OPERATOR_BINDING
            need(len(parts) == 5 and parts[1] == 'match-retained-acquire'
                 and parts[2] == profile['operation_id']
                 and parts[4] == profile['parent_operation_id'], 'operator_dictionary_scope')
            need(core.SHA_RE.fullmatch(parts[0]) is not None, 'retained_source_sha')
            need(os.environ.get('GITHUB_RUN_ATTEMPT', '1') == '1', 'retained_first_attempt_only')
            return dict(source_sha=parts[0], mode=parts[1], operation_id=parts[2],
                        cohort=parts[3], target_operation_id=parts[4])
        target_mode = parts[1] in ('match-retained-readback','match-retained-project','match-retained-current','match-retained-acquire','match-retained-anex-write','match-retained-anex-write-v2')
        need(len(parts) == (5 if target_mode else 4), 'retained_command_arity')
        source, mode, operation, cohort = parts[:4]
        if operation==PROOF_SOURCE_INSPECTION['operation_id']:
            need(mode=='match-retained-readback' and cohort==PROOF_SOURCE_INSPECTION['cohort']
                 and parts[4]==PROOF_SOURCE_INSPECTION['target_operation_id'], 'proof_source_inspection_scope')
        policy = continuation_policy(cohort)
        readback = SAVED_DETAIL_READBACKS.get(operation) if mode=='match-retained-readback' else None
        pinned_readback = readback is not None and readback['cohort']==cohort
        if policy is not NEXT_SCOPE:need(mode in ('match-retained-acquire','match-retained-anex-write-v2') or pinned_readback,'owner60_mode_scope')
        need(core.SHA_RE.fullmatch(source) is not None, 'retained_source_sha')
        prefix = 'retained-readback' if mode=='match-retained-readback' else 'retained-project' if mode=='match-retained-project' else 'retained-current' if mode=='match-retained-current' else 'retained-acquire' if mode=='match-retained-acquire' else 'retained-anex-write' if mode in ('match-retained-anex-write','match-retained-anex-write-v2') else 'retained' if mode == 'match-retained-intake' else 'quota5000'
        need(re.fullmatch(r'int-andromeda-match-' + prefix + r'-[0-9]{8}-v[1-9][0-9]*', operation) is not None,
             'retained_operation_scope')
        continuation = cohort == policy['cohort'] and mode in ('match-retained-acquire','match-retained-anex-write-v2','match-retained-readback')
        need(cohort == MODES[mode] or continuation, 'retained_cohort')
        transport_diagnostic = operation == TRANSPORT_DIAGNOSTIC['operation_id']
        if transport_diagnostic:
            need(continuation and mode == 'match-retained-readback', 'transport_diagnostic_scope')
        if continuation:
            selector_projection=mode=='match-retained-readback' and operation=='int-andromeda-match-retained-readback-20261020-v4'
            need(transport_diagnostic or selector_projection or pinned_readback or operation == policy['operation_id' if mode=='match-retained-acquire' else 'readback_operation_id' if mode=='match-retained-readback' else 'write_operation_id'], 'continuation_operation')
            expected_target = readback['target_operation_id'] if pinned_readback else TRANSPORT_DIAGNOSTIC['target_operation_id'] if transport_diagnostic else policy['parent_operation_id' if mode=='match-retained-acquire' else 'saved_acquisition_operation_id' if mode=='match-retained-readback' else 'operation_id']
            need(parts[4] == expected_target, 'continuation_parent')
        if mode=='match-retained-acquire' and not continuation:need(operation=='int-andromeda-match-retained-acquire-20261020-v1','acquire_consumed_scope')
        if mode=='match-retained-anex-write':need(operation=='int-andromeda-match-retained-anex-write-20261020-v1','write_fixed_scope')
        if mode=='match-retained-anex-write-v2' and not continuation:need(operation=='int-andromeda-match-retained-anex-write-20261020-v2','write_reconciled_fixed_scope')
        need(os.environ.get('GITHUB_RUN_ATTEMPT', '1') == '1', 'retained_first_attempt_only')
        command = {'source_sha': source, 'mode': mode, 'operation_id': operation, 'cohort': cohort}
        if 'catalogue_sha256' in policy:
            command['match_retained_catalogue_sha256'] = policy['catalogue_sha256']
        if target_mode:
            family='retained-readback' if transport_diagnostic else 'retained-acquire' if mode in ('match-retained-anex-write','match-retained-anex-write-v2') or (continuation and mode=='match-retained-readback') else 'retained-current' if mode=='match-retained-acquire' else 'retained-project' if mode=='match-retained-current' else 'retained' if mode=='match-retained-project' else '(?:retained|quota5000)'
            need(re.fullmatch(r'int-andromeda-match-'+family+r'-[0-9]{8}-v[1-9][0-9]*', parts[4]) is not None,
                 'retained_readback_target')
            command['target_operation_id'] = parts[4]
        return command
    core.parse_command = parse


def bundle_source(source_root, catalogue=False):
    root = Path(source_root).resolve()
    out = io.BytesIO()
    hashes = {}
    need(set(SOURCE_HASHES) == set(SOURCE_FILES), 'retained_source_policy')
    with tarfile.open(fileobj=out, mode='w:gz', format=tarfile.PAX_FORMAT) as archive:
        for relative in SOURCE_FILES:
            path = root / relative
            need(path.is_file() and not path.is_symlink() and path.resolve() == path
                 and path.stat().st_nlink == 1, 'retained_source_path')
            raw = path.read_bytes()
            need(0 < len(raw) <= 1024*1024, 'retained_source_size')
            digest = hashlib.sha256(raw).hexdigest()
            need(digest == SOURCE_HASHES[relative], 'retained_reviewed_source_changed')
            hashes[relative] = digest
            info = tarfile.TarInfo(relative)
            info.size = len(raw); info.mode = 0o600; info.mtime = 0
            archive.addfile(info, io.BytesIO(raw))
        if catalogue:
            raw, _ = catalogue_snapshot()
            hashes[CATALOGUE_PATH] = hashlib.sha256(raw).hexdigest()
            info = tarfile.TarInfo(CATALOGUE_PATH)
            info.size=len(raw);info.mode=0o600;info.mtime=0
            archive.addfile(info,io.BytesIO(raw))
        raw = json.dumps({'schema_version': 1, 'files': hashes}, sort_keys=True, separators=(',', ':')).encode()
        info = tarfile.TarInfo('manifest.json')
        info.size = len(raw); info.mode = 0o600; info.mtime = 0
        archive.addfile(info, io.BytesIO(raw))
    return out.getvalue(), hashes


REMOTE_HANDLER = r'''
def retained_next_scope(stage):
    if 'catalogue_sha256' in NEXT_SCOPE:
        path=stage/NEXT_SCOPE['path']
        if not safe_file(path,1048576) or hashlib.sha256(path.read_bytes()).hexdigest()!=NEXT_SCOPE['catalogue_sha256']:fail('catalogue_stage_binding')
        catalogue=safe_json(path,1048576)
        if (not isinstance(catalogue,dict) or set(catalogue)!={'schema_version','kind','scopes'}
                or type(catalogue.get('schema_version')) is not int or catalogue['schema_version']!=1
                or catalogue.get('kind')!='retained_never_selected_catalogue'
                or payload.get('match_retained_catalogue_sha256')!=NEXT_SCOPE['catalogue_sha256']):fail('catalogue_stage_shape')
        members=[x for x in catalogue.get('scopes',[]) if x.get('cohort')==NEXT_SCOPE['cohort']]
        expected={k:v for k,v in NEXT_SCOPE.items() if k not in ('path','sha256','catalogue_sha256')}
        if len(members)!=1 or members[0]!=expected or hashlib.sha256(json.dumps(members[0],sort_keys=True,separators=(',',':')).encode()).hexdigest()!=NEXT_SCOPE['sha256']:fail('catalogue_stage_member')
        return members[0]
    path=stage/NEXT_SCOPE['path']
    if not safe_file(path,262144) or hashlib.sha256(path.read_bytes()).hexdigest()!=NEXT_SCOPE['sha256']:fail('continuation_scope_file')
    value=safe_json(path,262144)
    if any(value.get(k)!=v for k,v in NEXT_SCOPE.items() if k not in ('path','sha256')):fail('continuation_scope_policy')
    return value

def retained_prior_selection(scope):
    if 'exclusion_history' in scope:
        history=scope['exclusion_history'];contexts=scope.get('context_history',[]);seen=set();receipts={}
        if not isinstance(history,list) or not isinstance(contexts,list) or not 1<=len(history)+len(contexts)<=100:fail('continuation_history_shape')
        base_count=len(history)
        for index,entry in enumerate(history+contexts):
            name=entry.get('operation_id','');ids=entry.get('selected_tv_hotel_ids')
            if (not re.fullmatch(r'int-andromeda-match-retained-acquire-[0-9]{8}-v[1-9][0-9]*',name)
                    or name in receipts or not isinstance(ids,list) or not ids
                    or any(type(v) is not int or v<1 for v in ids) or ids!=sorted(set(ids))
                    or (index<base_count and seen.intersection(ids))
                    or (index>=base_count and (not (scope.get('context_origin')=='owner_20261020_1257_timeout60_new_window'
                        or (scope.get('scope_kind')=='never_selected_bounded' and scope.get('context_origin')=='saved_user_search_exact_date_nights'))
                        or not set(ids).issubset(seen)))
                    or (scope.get('scope_kind')!='owner_date_variation' and set(ids).intersection(scope['selected_tv_hotel_ids']))
                    or entry.get('acquisition_state') not in ('completed','partial_no_replay')
                    or type(entry.get('unknown_http_calls')) is not int or entry['unknown_http_calls']<0):fail('continuation_history_shape')
            target=private/name
            if target.is_symlink() or target.resolve()!=target:fail('continuation_prior_path')
            wrapper=safe_json(target/'result.json',4194304);reservation=safe_json(target/'reservation.json',65536)
            data=wrapper.get('match_retained',{}).get('data',{})
            digest=hashlib.sha256(json.dumps(data,sort_keys=True,separators=(',',':')).encode()).hexdigest()
            if (wrapper.get('status')!='complete' or wrapper.get('mode')!='match-retained-acquire'
                    or wrapper.get('production_unchanged') is not True
                    or any(data.get(k)!=entry[k] or type(data.get(k)) is not type(entry[k]) for k in ('acquisition_state','unknown_http_calls'))
                    or any(x.get('operation_id')!=name for x in (wrapper,reservation,data))
                    or wrapper.get('source_sha')!=reservation.get('source_sha') or data.get('source_sha')!=reservation.get('source_sha')
                    or digest!=entry.get('evidence_sha256') or digest!=wrapper.get('match_retained',{}).get('evidence_sha256')
                    or any(type(x.get(k)) is not int or x[k]!=0 for x in (wrapper,data) for k in ('database_writes','mapping_writes'))):fail('continuation_prior_binding')
            plan=safe_json(target/'acquisition-plan.json',1048576)
            if plan.get('hotel_ids')!=ids or data.get('selected_hotels')!=len(ids):fail('continuation_prior_roster')
            receipts[name]=hashlib.sha256((target/'result.json').read_bytes()).hexdigest();seen.update(ids)
        if sorted(seen)!=scope['prior_selected_hotel_ids']:fail('continuation_history_union')
        return receipts
    name=scope['prior_acquisition_operation_id'];target=private/name
    if target.is_symlink() or target.resolve()!=target:fail('continuation_prior_path')
    wrapper=safe_json(target/'result.json',4194304);reservation=safe_json(target/'reservation.json',65536)
    data=wrapper.get('match_retained',{}).get('data',{})
    digest=hashlib.sha256(json.dumps(data,sort_keys=True,separators=(',',':')).encode()).hexdigest()
    if (wrapper.get('status')!='complete' or wrapper.get('mode')!='match-retained-acquire'
            or wrapper.get('production_unchanged') is not True or data.get('acquisition_state')!='completed'
            or data.get('unknown_http_calls')!=0 or any(x.get('operation_id')!=name for x in (wrapper,reservation,data))
            or wrapper.get('source_sha')!=reservation.get('source_sha') or data.get('source_sha')!=reservation.get('source_sha')
            or digest!=scope['prior_acquisition_evidence_sha256'] or digest!=wrapper.get('match_retained',{}).get('evidence_sha256')):fail('continuation_prior_binding')
    plan=safe_json(target/'acquisition-plan.json',1048576)
    if plan.get('hotel_ids')!=scope['prior_selected_hotel_ids']:fail('continuation_prior_roster')
    return hashlib.sha256((target/'result.json').read_bytes()).hexdigest()

def retained_effective_requests(scope):
    retained_prior_selection(scope)
    requests=[]
    for entry in scope['exclusion_history']+scope.get('context_history',[]):
        target=private/entry['operation_id'];wrapper=safe_json(target/'result.json',4194304)
        data=wrapper['match_retained']['data'];count=data.get('physical_http_reservations')
        if type(count) is not int or not 0<=count<=400:fail('context_prior_request_count')
        starts=0
        for number in range(1,count+1):
            path=target/('request-%04d.json'%number)
            value=safe_json(path,65536)
            if value.get('action')!='search_start':continue
            p=value.get('params',{});ids=p.get('hotelIds');starts+=1
            if (value.get('path')!='/tours/search' or not isinstance(ids,list) or not 1<=len(ids)<=30
                    or any(type(h) is not int or h<1 for h in ids) or len(ids)!=len(set(ids))
                    or not set(ids).issubset(entry['selected_tv_hotel_ids']) or p.get('operatorIds')!=[13]
                    or any(type(p.get(k)) is not int for k in ('countryId','departureId','adults','nightsFrom','nightsTo'))
                    or p.get('childs')!=[] or any(not re.fullmatch(r'[0-9]{4}-[0-9]{2}-[0-9]{2}',str(p.get(k,''))) for k in ('dateFrom','dateTo'))):fail('context_prior_request_binding')
            requests.append({'operation_id':entry['operation_id'],'request_number':number,
                             'request_sha256':hashlib.sha256(path.read_bytes()).hexdigest(),'params':p})
        if starts!=data.get('tariff_search_units') or starts!=data.get('actions',{}).get('search_start',0):fail('context_prior_start_count')
    return requests

def retained_completed_bounded_batch(data,count):
    # acquire() records completion per batch, never as a top-level search_complete flag.
    batches=data.get('batches');actions=data.get('actions');edges=data.get('edges')
    if (type(count) is not int or not 1<=count<=25 or not isinstance(batches,list) or len(batches)!=1
            or not isinstance(batches[0],dict) or not isinstance(actions,dict) or not isinstance(edges,list)
            or data.get('stop_reason') is not None):return False
    batch=batches[0]
    if (type(batch.get('batch')) is not int or batch['batch']!=1 or type(batch.get('sent')) is not int
            or batch['sent']!=count or batch.get('search_complete') is not True
            or type(batch.get('returned_anex_hotels')) is not int or not 0<=batch['returned_anex_hotels']<=count
            or set(actions)-{'search_start','search_status','search_results','tour_detail'}
            or any(type(n) is not int or n<0 for n in actions.values())
            or actions.get('search_start')!=1 or actions.get('search_results')!=1
            or not 1<=actions.get('search_status',0)<=3
            or actions.get('tour_detail',0)!=batch['returned_anex_hotels']
            or len(edges)!=batch['returned_anex_hotels'] or sum(actions.values())!=data.get('supplier_calls')
            or data.get('physical_http_reservations')!=data.get('supplier_calls')
            or data.get('tariff_search_units')!=1):return False
    ids=[];states={}
    for edge in edges:
        if not isinstance(edge,dict) or type(edge.get('tv_hotel_id')) is not int or not isinstance(edge.get('state'),str):return False
        ids.append(edge['tv_hotel_id']);states[edge['state']]=states.get(edge['state'],0)+1
    return len(set(ids))==len(ids) and states==data.get('edge_states')

def retained_catalogue_history(scope,stage):
    # Reconcile only known immutable sibling IDs, never list/census the private root.
    retained_next_scope(stage)
    members=safe_json(stage/NEXT_SCOPE['path'],1048576)['scopes']
    checked=json.loads(json.dumps(scope));snapshots={}
    ledger=safe_json(pathlib.Path(os.environ['HOME'])/'.anytoour-match/retained-selection-claims.json',8*1024*1024)
    if ledger.get('schema_version')!=1 or not isinstance(ledger.get('claims'),dict):fail('catalogue_hotel_ledger_shape')
    known=set(scope['prior_selected_hotel_ids'])
    for sibling in members:known.update(sibling['selected_tv_hotel_ids'])
    for key in ledger['claims']:
        if not key.startswith('TOURVISOR_ANEX_JWT:13:'):continue
        if not re.fullmatch(r'TOURVISOR_ANEX_JWT:13:[1-9][0-9]*',key) or int(key.rsplit(':',1)[1]) not in known:fail('catalogue_unreconciled_claim_no_replay')
    for sibling in members:
        name=sibling['operation_id']
        if name==operation:continue
        target=private/name
        if target.is_symlink() or target.resolve()!=target:fail('catalogue_sibling_path')
        if not target.exists():
            if any('TOURVISOR_ANEX_JWT:13:'+str(h) in ledger['claims'] for h in sibling['selected_tv_hotel_ids']):fail('catalogue_sibling_unknown_no_replay')
            snapshots[name]=None;continue
        if not target.is_dir() or not safe_file(target/'result.json',4194304):fail('catalogue_sibling_unknown_no_replay')
        wrapper=safe_json(target/'result.json',4194304);saved=safe_json(target/'reservation.json',65536)
        data=wrapper.get('match_retained',{}).get('data',{})
        # Historical whole-catalogue digest may differ after an append-only config update.
        # Its retained source manifest and exact unchanged member remain authoritative.
        old_path=target/'source'/NEXT_SCOPE['path'];old_manifest=target/'source/manifest.json'
        if not safe_file(old_path,1048576) or not safe_file(old_manifest,65536):fail('catalogue_sibling_source')
        old_raw=old_path.read_bytes();old_digest=hashlib.sha256(old_raw).hexdigest()
        old_cat=safe_json(old_path,1048576);old_files=safe_json(old_manifest,65536)
        old_members=[x for x in old_cat.get('scopes',[]) if x.get('cohort')==sibling['cohort']]
        if old_files.get('files',{}).get(NEXT_SCOPE['path'])!=old_digest or old_members!=[sibling]:fail('catalogue_sibling_source')
        member_sha=hashlib.sha256(json.dumps(sibling,sort_keys=True,separators=(',',':')).encode()).hexdigest()
        if (wrapper.get('status')!='complete' or wrapper.get('mode')!='match-retained-acquire'
                or data.get('acquisition_state')!='completed' or type(data.get('unknown_http_calls')) is not int or data['unknown_http_calls']!=0
                or data.get('scope_id')!=sibling['scope_id'] or data.get('continuation_scope_sha256')!=member_sha
                or data.get('continuation_catalogue_sha256')!=old_digest
                or data.get('cohort')!=sibling['cohort'] or data.get('target_operation_id')!=scope['parent_operation_id']
                or data.get('parent_evidence_sha256')!=scope['parent_evidence_sha256']
                or data.get('wire_context')!=sibling['wire_context'] or data.get('context_origin')!=sibling['context_origin']
                or data.get('selected_hotels')!=sibling['selected_count']
                or data.get('selected_id_sha256')!=hashlib.sha256(json.dumps(sibling['selected_tv_hotel_ids'],separators=(',',':')).encode()).hexdigest()
                or type(data.get('supplier_calls')) is not int or not 0<=data['supplier_calls']<=30
                or type(data.get('physical_http_reservations')) is not int or data['physical_http_reservations']!=data['supplier_calls']
                or type(data.get('tariff_search_units')) is not int or not 0<=data['tariff_search_units']<=1
                or not retained_completed_bounded_batch(data,sibling['selected_count'])
                or any(e['tv_hotel_id'] not in sibling['selected_tv_hotel_ids'] for e in data['edges'])
                or data.get('safe_to_write_now') is not False
                or any(x.get('operation_id')!=name for x in (wrapper,saved,data))):fail('catalogue_sibling_unknown_no_replay')
        digest=hashlib.sha256(json.dumps(data,sort_keys=True,separators=(',',':')).encode()).hexdigest()
        checked['exclusion_history'].append(dict(operation_id=name,selected_tv_hotel_ids=sibling['selected_tv_hotel_ids'],
             acquisition_state='completed',unknown_http_calls=0,evidence_sha256=digest))
        checked['prior_selected_hotel_ids']=sorted(set(checked['prior_selected_hotel_ids'])|set(sibling['selected_tv_hotel_ids']))
        snapshots[name]={'result':hashlib.sha256((target/'result.json').read_bytes()).hexdigest(),
                         'reservation':hashlib.sha256((target/'reservation.json').read_bytes()).hexdigest(),
                         'catalogue':old_digest,'manifest':hashlib.sha256(old_manifest.read_bytes()).hexdigest()}
    # Existing history guards check source/reservation/evidence/production/plan and every physical request.
    requests=retained_effective_requests(checked)
    siblings={x['operation_id']:x for x in members}
    for request in requests:
        sibling=siblings.get(request['operation_id'])
        if sibling is None:continue
        p=request['params'];wire=sibling['wire_context']
        if (p['hotelIds']!=sibling['selected_tv_hotel_ids'] or p['countryId']!=sibling['country_id']
                or p['departureId']!=sibling['departure_id'] or p['adults']!=sibling['adults']
                or p['dateFrom']!=wire['date_from'] or p['dateTo']!=wire['date_to']
                or p['nightsFrom']!=wire['nights_from'] or p['nightsTo']!=wire['nights_to']):fail('catalogue_sibling_wire')
    return snapshots,requests

def retained_transport_diagnostic(stage):
    import datetime as dt
    profile=TRANSPORT_DIAGNOSTIC
    if (operation!=profile['operation_id'] or payload.get('target_operation_id')!=profile['target_operation_id']
            or payload.get('cohort')!=NEXT_SCOPE['cohort']):fail('transport_diagnostic_scope')
    scope=retained_next_scope(stage);retained_prior_selection(scope)
    protected={}
    def read(path,bound):
        if (not safe_file(path,bound) or path.resolve()!=path or path.stat().st_nlink!=1):fail('transport_evidence_path')
        before=path.stat();raw=path.read_bytes();after=path.stat()
        signature=lambda s:(s.st_dev,s.st_ino,s.st_size,s.st_mtime_ns,s.st_ctime_ns)
        if signature(before)!=signature(after) or len(raw)!=after.st_size:fail('transport_evidence_drift')
        protected[path]=(hashlib.sha256(raw).hexdigest(),signature(after))
        return raw,after
    failed=private/profile['target_operation_id']
    if failed.is_symlink() or failed.resolve()!=failed:fail('transport_failed_path')
    failed_raw,_=read(failed/'result.json',65536)
    failed_result=json.loads(failed_raw)
    failed_reservation=json.loads(read(failed/'reservation.json',65536)[0])
    if (hashlib.sha256(failed_raw).hexdigest()!=profile['failed_receipt_sha256']
            or any(x.get('operation_id')!=profile['target_operation_id']
                   or x.get('source_sha')!=profile['failed_source_sha']
                   or x.get('mode')!='match-retained-readback' for x in (failed_result,failed_reservation))
            or failed_result.get('status')!='unknown_no_replay'
            or failed_result.get('reason')!="name 'Path' is not defined"
            or any(type(failed_result.get(k)) is not int or failed_result[k]!=0
                   for k in ('supplier_calls','database_writes','mapping_writes','lead_calls','booking_calls'))):fail('transport_failed_binding')
    rows=[];parents=[]
    for pin in profile['parents']:
        name='int-andromeda-match-retained-acquire-20261020-v'+str(pin['version'])
        target=private/name
        if target.is_symlink() or target.resolve()!=target:fail('transport_parent_path')
        raw,terminal=read(target/'result.json',4194304);wrapper=json.loads(raw)
        reservation=json.loads(read(target/'reservation.json',65536)[0]);data=wrapper.get('match_retained',{}).get('data',{})
        evidence=hashlib.sha256(json.dumps(data,sort_keys=True,separators=(',',':')).encode()).hexdigest()
        if (wrapper.get('status')!='complete' or wrapper.get('mode')!='match-retained-acquire'
                or wrapper.get('production_unchanged') is not True
                or any(x.get('operation_id')!=name for x in (wrapper,reservation,data))
                or wrapper.get('source_sha')!=reservation.get('source_sha') or data.get('source_sha')!=reservation.get('source_sha')
                or evidence!=pin['evidence'] or evidence!=wrapper.get('match_retained',{}).get('evidence_sha256')
                or any(type(x.get(k)) is not int or x[k]!=0 for x in (wrapper,data) for k in ('database_writes','mapping_writes'))
                or any(type(x.get('supplier_calls')) is not int or x['supplier_calls']!=pin['calls'] for x in (wrapper,data))
                or type(data.get('physical_http_reservations')) is not int or data['physical_http_reservations']!=pin['calls']
                or type(data.get('unknown_http_calls')) is not int or data['unknown_http_calls']!=pin['unknown']
                or data.get('acquisition_state')!=('partial_no_replay' if pin['unknown'] else 'completed')):fail('transport_parent_binding')
        if len(list(target.glob('request-*.json')))!=pin['calls']:fail('transport_request_count')
        actions={};missing=0
        for n in range(1,pin['calls']+1):
            req_path=target/('request-%04d.json'%n);req_raw,stamp=read(req_path,65536);request=json.loads(req_raw)
            action=request.get('action')
            if action not in ('search_start','search_status','search_results','tour_detail'):fail('transport_request_action')
            actions[action]=actions.get(action,0)+1
            row={'operation_id':name,'request_number':n,'action':action,'request_sha256':hashlib.sha256(req_raw).hexdigest(),
                 'request_file_mtime_utc':dt.datetime.fromtimestamp(stamp.st_mtime_ns/1e9,dt.timezone.utc).isoformat(),
                 'failure_phase':'not_recorded','http_status':None,'response_capture_lag_ms':None,'request_to_terminal_file_ms':None}
            meta_path=target/('response-%04d.json'%n);body_path=target/('response-%04d.bin'%n)
            if meta_path.is_symlink() or body_path.is_symlink():fail('transport_response_path')
            if meta_path.exists()!=body_path.exists():fail('transport_response_pair')
            if meta_path.exists():
                meta_raw,captured=read(meta_path,65536);meta=json.loads(meta_raw)
                if (type(meta.get('http_status')) is not int or not 100<=meta['http_status']<=599
                        or not re.fullmatch(r'[a-f0-9]{64}',str(meta.get('raw_sha256','')))
                        or not safe_file(body_path,16*1024*1024+1) or body_path.stat().st_nlink!=1):fail('transport_response_metadata')
                lag=captured.st_mtime_ns-stamp.st_mtime_ns
                if lag<0:fail('transport_evidence_clock')
                row.update(http_status=meta['http_status'],failure_phase=None,response_capture_lag_ms=round(lag/1e6,3),
                           response_sha256=meta['raw_sha256'],response_bytes=body_path.stat().st_size)
            else:
                missing+=1;lag=terminal.st_mtime_ns-stamp.st_mtime_ns
                if lag<0:fail('transport_evidence_clock')
                row['request_to_terminal_file_ms']=round(lag/1e6,3)
            rows.append(row)
        if missing!=pin['unknown'] or actions!=data.get('actions'):fail('transport_unknown_or_action_binding')
        parents.append({'operation_id':name,'receipt_sha256':hashlib.sha256(raw).hexdigest(),'evidence_sha256':evidence,
                        'recorded_http_attempts':pin['calls'],'recorded_unknown':pin['unknown'],'stop_reason':data.get('stop_reason')})
    home=pathlib.Path.home();account_root=home/'.anytoour-match'
    ledgers={}
    for label,path in [('hotel_selection',account_root/'retained-selection-claims.json'),
                       ('context_selection',account_root/'retained-context-claims.json'),
                       ('account_day',account_root/'provider-quotas/tourvisor-anex-2026-10-20.json')]:
        raw,_=read(path,8*1024*1024);json.loads(raw);ledgers[label]=hashlib.sha256(raw).hexdigest()
    config=project/'config.php';config_raw,config_stat=read(config,1048576)
    php=r"""require $argv[1];$a=defined('TOURVISOR_ANEX_JWT')?constant('TOURVISOR_ANEX_JWT'):getenv('TOURVISOR_ANEX_JWT');$b=defined('TOURVISOR_JWT')?constant('TOURVISOR_JWT'):getenv('TOURVISOR_JWT');$p=explode('.',trim((string)$a));$claims=null;if(count($p)===3){$part=strtr($p[1],'-_','+/');$raw=base64_decode($part.str_repeat('=',(4-strlen($part)%4)%4),true);if($raw!==false)$claims=json_decode($raw,true);}$exp=is_array($claims)&&isset($claims['exp'])&&is_int($claims['exp'])?$claims['exp']:null;$now=time();echo json_encode(['configured'=>is_string($a)&&trim($a)!=='','matches_default_token'=>is_string($a)&&$a!==''&&is_string($b)&&hash_equals($a,$b),'jwt_claims_parseable'=>is_array($claims),'expiry_epoch_seconds'=>$exp,'expired_at_capture'=>$exp===null?null:$exp<=$now,'capture_epoch_seconds'=>$now],JSON_THROW_ON_ERROR);"""
    env={k:os.environ[k] for k in ('HOME','PATH','LANG','LC_ALL') if k in os.environ}
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0','-d','disable_functions='+disabled,
                        '-r',php,'--',str(config)],cwd=project,env=env,capture_output=True,text=True,timeout=15)
    if run.returncode or run.stderr.strip() or len(run.stdout)>4096:fail('transport_config_metadata_failed')
    account=json.loads(run.stdout)
    if (not isinstance(account,dict) or set(account)!={'configured','matches_default_token','jwt_claims_parseable','expiry_epoch_seconds','expired_at_capture','capture_epoch_seconds'}
            or any(type(account.get(k)) is not bool for k in ('configured','matches_default_token','jwt_claims_parseable'))
            or type(account.get('capture_epoch_seconds')) is not int
            or (account.get('expiry_epoch_seconds') is not None and type(account['expiry_epoch_seconds']) is not int)
            or (account.get('expired_at_capture') is not None and type(account['expired_at_capture']) is not bool)
            or (account['expiry_epoch_seconds'] is None)!=(account['expired_at_capture'] is None)
            or (account['expiry_epoch_seconds'] is not None and account['expired_at_capture']!=(account['expiry_epoch_seconds']<=account['capture_epoch_seconds']))):fail('transport_config_metadata_shape')
    for path,(digest,signature) in protected.items():
        raw,stamp=read(path,8*1024*1024)
        if hashlib.sha256(raw).hexdigest()!=digest or (stamp.st_dev,stamp.st_ino,stamp.st_size,stamp.st_mtime_ns,stamp.st_ctime_ns)!=signature:fail('transport_protected_drift')
    return {'state':'retained_transport_inspected_read_only','target_operation_id':profile['target_operation_id'],
            'failed_operation_receipt_sha256':profile['failed_receipt_sha256'],'failed_operation_replayed':False,
            'failed_operation_status':'unknown_no_replay',
            'continuation_scope_sha256':NEXT_SCOPE['sha256'],'scope_id':scope['scope_id'],'parents':parents,'requests':rows,
            'known_lineage_http_attempts':sum(p['calls'] for p in profile['parents']),
            'known_lineage_unknown':sum(p['unknown'] for p in profile['parents']),
            'account_selector':'TOURVISOR_ANEX_JWT','current_account_metadata':account,'metadata_signature_verified':False,
            'current_config_sha256':hashlib.sha256(config_raw).hexdigest(),'config_metadata_is_current_not_historical':True,
            'config_file_mtime_utc':dt.datetime.fromtimestamp(config_stat.st_mtime_ns/1e9,dt.timezone.utc).isoformat(),
            'protected_ledger_sha256':ledgers,'protected_inputs_unchanged':True,'raw_response_bodies_read':False,
            'timing_scope':'filesystem capture lag includes local work/fsync; terminal lag is not network latency',
            'historical_network_phase_recorded':False,'cause_established':False,
            'supplier_calls':0,'database_writes':0,'mapping_writes':0,'new_accepted_mappings':0,'safe_to_write_now':False}

def retained_saved_detail(stage):
    scope=retained_next_scope(stage);retained_prior_selection(scope)
    profile=SAVED_DETAIL_READBACKS.get(operation)
    protected={}
    if profile:
        import pathlib
        if payload.get('cohort')!=profile['cohort']:fail('saved_detail_profile_scope')
        target=private/profile['target_operation_id']
        paths=[target/'result.json',target/'reservation.json',target/('response-%04d.bin'%profile['response_number']),
               target/('response-%04d.json'%profile['response_number']),target/('request-%04d.json'%profile['response_number'])]
        home=pathlib.Path.home()/'.anytoour-match'
        ledgers={'hotel_selection':home/'retained-selection-claims.json','context_selection':home/'retained-context-claims.json',
                 'account_day':home/'provider-quotas/tourvisor-anex-2026-10-20.json'}
        for path in paths+list(ledgers.values()):
            if not safe_file(path,16*1024*1024) or path.stat().st_nlink!=1:fail('saved_detail_protected_path')
            protected[path]=hashlib.sha256(path.read_bytes()).hexdigest()
        if (protected[ledgers['hotel_selection']]!=profile['hotel_ledger_sha256']
                or protected[ledgers['context_selection']]!=profile['context_ledger_sha256']):fail('saved_detail_ledger_binding')
        wrapper=safe_json(target/'result.json',4194304);data=wrapper.get('match_retained',{}).get('data',{})
        reservation=safe_json(target/'reservation.json',65536)
        if (protected[target/'result.json']!=profile['receipt_sha256']
                or wrapper.get('match_retained',{}).get('evidence_sha256')!=profile['evidence_sha256']
                or hashlib.sha256(json.dumps(data,sort_keys=True,separators=(',',':')).encode()).hexdigest()!=profile['evidence_sha256']
                or wrapper.get('status')!='complete' or wrapper.get('mode')!='match-retained-acquire'
                or wrapper.get('production_unchanged') is not True or data.get('acquisition_state')!='completed'
                or data.get('unknown_http_calls')!=0 or data.get('selected_hotels')!=scope['selected_count']
                or data.get('continuation_scope_sha256')!=NEXT_SCOPE['sha256'] or data.get('scope_id')!=scope['scope_id']
                or any(x.get('operation_id')!=profile['target_operation_id'] or x.get('source_sha')!=profile['source_sha'] for x in (wrapper,data,reservation))
                or any(type(x.get(k)) is not int or x[k]!=0 for x in (wrapper,data) for k in ('database_writes','mapping_writes'))):fail('saved_detail_acquisition_binding')
    if operation=='int-andromeda-match-retained-readback-20261020-v4':
        prior=safe_json(private/scope['readback_operation_id']/'result.json',1048576)
        value=prior.get('match_retained',{}).get('data',{})
        expected='2947fbf11d3dfa727d0890616082401edbccae2f900b799a6182cddf3353e74c'
        if (prior.get('status')!='complete' or prior.get('mode')!='match-retained-readback'
                or value.get('state')!='saved_detail_inspected_read_only' or value.get('tv_hotel_id')!=8038
                or value.get('continuation_scope_sha256')!=NEXT_SCOPE['sha256']
                or prior.get('match_retained',{}).get('evidence_sha256')!=expected
                or hashlib.sha256(json.dumps(value,sort_keys=True,separators=(',',':')).encode()).hexdigest()!=expected
                or any(type(x.get(k)) is not int or x[k]!=0 for x in (prior,value) for k in ('supplier_calls','database_writes','mapping_writes'))):fail('selector_projection_prior_inspection')
    name=profile['target_operation_id'] if profile else scope['saved_acquisition_operation_id'];target=private/name
    if payload.get('target_operation_id')!=name:fail('saved_detail_target')
    wrapper=safe_json(target/'result.json',4194304);data=wrapper['match_retained']['data']
    if wrapper['match_retained']['evidence_sha256']!=(profile['evidence_sha256'] if profile else scope['saved_acquisition_evidence_sha256']):fail('saved_detail_receipt')
    hotel=profile['tv_hotel_id'] if profile else 8038
    edges=[e for e in data['edges'] if e.get('tv_hotel_id')==hotel]
    if len(edges)!=1:fail('saved_detail_edge')
    edge=edges[0];raw_path=target/'response-0004.bin';meta=safe_json(target/'response-0004.json',65536)
    request=safe_json(target/'request-0004.json',65536)
    if not safe_file(raw_path,16*1024*1024):fail('saved_detail_path')
    raw=raw_path.read_bytes();digest=hashlib.sha256(raw).hexdigest()
    if (digest!=(profile['raw_sha256'] if profile else 'b6bc670f30163350d5940b2f29bc79d35f4b74c0090f4d255daaef2b84889328')
            or digest!=edge.get('detail_raw',{}).get('raw_sha256') or edge.get('detail_raw',{}).get('response_number')!=4
            or meta.get('raw_sha256')!=digest or meta.get('complete_body') is not True or meta.get('http_status')!=200
            or request.get('action')!='tour_detail' or not re.fullmatch(r'/tours/[1-9][0-9]{0,21}',str(request.get('path','')))
            or hashlib.sha256(request['path'].split('/')[-1].encode()).hexdigest()!=edge.get('tour_id_sha256')):fail('saved_detail_raw_binding')
    import sys
    sys.path.insert(0,str(stage/'scripts/diagnostics'))
    import hotel_match_retained_acquire_v1 as proof
    detail=proof.shape.unwrap(json.loads(raw))
    if profile and (proof.shape.ident(detail,'hotel')!=hotel or proof.shape.ident(detail,'operator')!=13):fail('saved_detail_identity')
    out=proof.inspect_saved_detail(detail,hotel)
    if hashlib.sha256(raw_path.read_bytes()).hexdigest()!=digest:fail('saved_detail_drift')
    for path,expected in protected.items():
        if not safe_file(path,16*1024*1024) or hashlib.sha256(path.read_bytes()).hexdigest()!=expected:fail('saved_detail_protected_drift')
    return dict(state='saved_detail_inspected_read_only',target_operation_id=name,tv_hotel_id=hotel,
                raw_sha256=digest,raw_bytes=len(raw),inspection=out,supplier_calls=0,database_writes=0,mapping_writes=0,
                new_accepted_mappings=0,safe_to_write_now=False,source_partial_is_not_write_authority=True,
                **({'source_acquisition_receipt_sha256':profile['receipt_sha256'],'protected_inputs_unchanged':True,
                    'protected_ledger_sha256':{label:protected[path] for label,path in ledgers.items()}} if profile else {}),
                continuation_scope_sha256=NEXT_SCOPE['sha256'],scope_id=scope['scope_id'])

def write_retained_anex_target(stage):
    continuation=retained_next_scope(stage) if payload.get('cohort')==NEXT_SCOPE['cohort'] else None
    if not continuation:
        reconciled=private/'int-andromeda-match-retained-anex-write-20261020-v1'
        if reconciled.is_symlink() or reconciled.resolve()!=reconciled:fail('write_reconciliation_path')
        prior_write=safe_json(reconciled/'result.json',1048576)
        old=prior_write.get('match_retained',{}).get('data',{})
        old_evidence='21f9f77b14a51e6935c1be9d4b7af0458c4e1e4df5ecf240d94709cefa8b47c7'
        if (prior_write.get('status')!='complete' or prior_write.get('production_unchanged') is not True or prior_write.get('mode')!='match-retained-anex-write'
                or old.get('state')!='completed_no_eligible' or old.get('new_accepted_mappings')!=0
                or any(x.get(k)!=0 for x in (prior_write,old) for k in ('supplier_calls','database_writes','mapping_writes'))
                or hashlib.sha256(json.dumps(old,sort_keys=True,separators=(',',':')).encode()).hexdigest()!=old_evidence
                or prior_write.get('match_retained',{}).get('evidence_sha256')!=old_evidence
                or (reconciled/'commit-attempt.json').exists()):fail('write_v1_not_reconciled_zero')
        reconciled_hash=hashlib.sha256((reconciled/'result.json').read_bytes()).hexdigest()
    name=payload.get('target_operation_id','')
    if name!=(continuation['operation_id'] if continuation else 'int-andromeda-match-retained-acquire-20261020-v1'):fail('write_acquisition_scope')
    target=private/name
    if target.is_symlink() or target.resolve()!=target:fail('write_acquisition_path')
    prior=safe_json(target/'result.json',4194304);reservation=safe_json(target/'reservation.json',65536)
    acquired=prior.get('match_retained',{}).get('data',{})
    expected=prior.get('match_retained',{}).get('evidence_sha256') if continuation else 'cb7f28c82d5df5b8f069f894ab978f198b2eebfc949034c8e371050c4f4e0aeb'
    if continuation and (acquired.get('continuation_scope_sha256')!=NEXT_SCOPE['sha256'] or acquired.get('scope_id')!=continuation['scope_id']
            or acquired.get('parent_evidence_sha256')!=continuation['parent_evidence_sha256']
            or acquired.get('selected_hotels')!=continuation['selected_count']
            or acquired.get('selected_id_sha256')!=hashlib.sha256(json.dumps(continuation['selected_tv_hotel_ids'],separators=(',',':')).encode()).hexdigest()):fail('write_continuation_binding')
    if 'catalogue_sha256' in NEXT_SCOPE and acquired.get('continuation_catalogue_sha256')!=NEXT_SCOPE['catalogue_sha256']:fail('write_catalogue_binding')
    if (prior.get('status')!='complete' or prior.get('mode')!='match-retained-acquire'
            or acquired.get('acquisition_state')!='completed' or acquired.get('unknown_http_calls')!=0
            or any(x.get('operation_id')!=name for x in (prior,reservation,acquired))
            or acquired.get('source_sha')!=reservation.get('source_sha') or prior.get('source_sha')!=reservation.get('source_sha')
            or hashlib.sha256(json.dumps(acquired,sort_keys=True,separators=(',',':')).encode()).hexdigest()!=expected
            or prior.get('match_retained',{}).get('evidence_sha256')!=expected
            or prior.get('production_unchanged') is not True
            or any(x.get(k)!=0 for x in (prior,acquired) for k in ('database_writes','mapping_writes'))):fail('write_acquisition_binding')
    parent_hash=hashlib.sha256((target/'result.json').read_bytes()).hexdigest()
    current_dir=private/(continuation['parent_operation_id'] if continuation else 'int-andromeda-match-retained-current-20261020-v1')
    current=safe_json(current_dir/'result.json',32*1024*1024)
    if hashlib.sha256((current_dir/'result.json').read_bytes()).hexdigest()!=acquired.get('parent_receipt_sha256'):fail('write_current_parent_drift')
    context=current.get('match_retained',{}).get('data',{})
    if hashlib.sha256(json.dumps(context,sort_keys=True,separators=(',',':')).encode()).hexdigest()!=acquired.get('parent_evidence_sha256'):fail('write_current_evidence')
    snapshots={r['tv_hotel_id']:r for r in context.get('rows',[])}
    import sys
    sys.path.insert(0,str(stage/'scripts/diagnostics'))
    import hotel_match_retained_acquire_v1 as proof
    pairs=[]
    for edge in acquired.get('edges',[]):
        if edge.get('state')!='verified_native_pair':continue
        raw_info=edge.get('detail_raw',{});number=raw_info.get('response_number')
        if type(number) is not int or not 1<=number<=400:fail('write_raw_number')
        raw_path=target/('response-%04d.bin'%number)
        if not safe_file(raw_path,16*1024*1024):fail('write_raw_path')
        raw=raw_path.read_bytes()
        if hashlib.sha256(raw).hexdigest()!=raw_info.get('raw_sha256'):fail('write_raw_hash')
        meta=safe_json(target/('response-%04d.json'%number),65536)
        request=safe_json(target/('request-%04d.json'%number),65536)
        if (meta.get('http_status')!=200 or meta.get('complete_body') is not True or meta.get('raw_sha256')!=raw_info['raw_sha256']
                or request.get('action')!='tour_detail' or not re.fullmatch(r'/tours/[1-9][0-9]{0,21}',str(request.get('path','')))
                or hashlib.sha256(request['path'].split('/')[-1].encode()).hexdigest()!=edge.get('tour_id_sha256')):fail('write_response_binding')
        detail=proof.shape.unwrap(json.loads(raw));verified=proof.native_pair(detail,edge['tv_hotel_id'])
        for key in ('state','anex_hotel_id','tv_hotel_id','namespace','operator_id','operator_link_host','operator_link_sha256'):
            if verified.get(key)!=edge.get(key):fail('write_native_proof_mismatch')
        snap=snapshots.get(edge['tv_hotel_id'])
        if not snap or snap.get('acquisition_ready') is not True:fail('write_target_snapshot')
        pairs.append({'edge':edge,'snapshot':snap})
    actual=sorted((p['edge']['anex_hotel_id'],p['edge']['tv_hotel_id']) for p in pairs)
    if not continuation and actual!=sorted([('9384',35297),('12901',1474),('8475',1014),('8476',1095),('14818',1015),('26591',3418),('19606',57027)]):fail('write_exact_seven_pairs')
    if continuation and (not pairs or len(pairs)>continuation['selected_count'] or any(p['edge']['tv_hotel_id'] not in continuation['selected_tv_hotel_ids'] for p in pairs)):fail('write_continuation_roster')
    manifest={'operation_id':operation,'acquisition_evidence_sha256':expected,'pairs':pairs}
    if continuation:manifest.update(continuation_scope_sha256=NEXT_SCOPE['sha256'],scope_id=continuation['scope_id'])
    else:manifest['reconciled_write_evidence_sha256']=old_evidence
    fd=os.open(op/'write-manifest.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:handle.write(json.dumps(manifest,sort_keys=True).encode());handle.flush();os.fsync(handle.fileno())
    config=project/'config.php'
    if not safe_file(config,1048576):fail('write_config_path')
    config_hash=hashlib.sha256(config.read_bytes()).hexdigest()
    env={k:os.environ[k] for k in ('HOME','PATH','LANG','LC_ALL') if k in os.environ}
    env.update(MATCH_RETAINED_DIR=str(op),MATCH_RETAINED_SOURCE_ROOT=str(stage),MATCH_RETAINED_SOURCE_SHA=source)
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0','-d','disable_functions='+disabled,
        str(stage/'scripts/diagnostics/hotel_match_retained_anex_write_v1.php')],cwd=project,env=env,capture_output=True,text=True,timeout=240)
    if len(run.stdout.encode())>1048576:fail('write_output_bound')
    fd=os.open(op/'write-raw.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:handle.write(run.stdout.encode());handle.flush();os.fsync(handle.fileno())
    try:data=json.loads(run.stdout)
    except Exception:fail('write_output_unknown_no_replay')
    result['database_writes']=data.get('database_writes');result['mapping_writes']=data.get('mapping_writes')
    if run.returncode or run.stderr.strip() or data.get('state') not in ('committed_verified','completed_no_eligible'):
        result['match_retained_write_failure']=data;fail('write_failed_no_replay')
    if (data.get('supplier_calls')!=0 or data.get('safe_to_write_now') is not False
            or hashlib.sha256((target/'result.json').read_bytes()).hexdigest()!=parent_hash
            or (not continuation and hashlib.sha256((reconciled/'result.json').read_bytes()).hexdigest()!=reconciled_hash)
            or hashlib.sha256(config.read_bytes()).hexdigest()!=config_hash):fail('write_post_binding_unknown_no_replay')
    data.update(operation_id=operation,source_sha=source,control_source_sha=payload['match_retained_control_sha'],
        cohort=payload['cohort'],target_operation_id=name,parent_receipt_sha256=parent_hash,
        acquisition_evidence_sha256=expected,raw_evidence_reverified_pairs=len(pairs),no_replay=True)
    if continuation:data.update(continuation_scope_sha256=NEXT_SCOPE['sha256'],scope_id=continuation['scope_id'])
    else:data.update(reconciled_write_evidence_sha256=old_evidence,reconciled_write_receipt_sha256=reconciled_hash)
    if 'catalogue_sha256' in NEXT_SCOPE:data['continuation_catalogue_sha256']=NEXT_SCOPE['catalogue_sha256']
    evidence=json.dumps(data,sort_keys=True,separators=(',',':')).encode()
    fd=os.open(op/'retained-evidence.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:handle.write(evidence);handle.flush();os.fsync(handle.fileno())
    return {'evidence_sha256':hashlib.sha256(evidence).hexdigest(),'data':data}

def acquire_retained_target(stage):
    continuation=retained_next_scope(stage) if payload.get('cohort')==NEXT_SCOPE['cohort'] else None
    name=payload.get('target_operation_id','')
    if name!=(continuation['parent_operation_id'] if continuation else 'int-andromeda-match-retained-current-20261020-v1'):fail('acquire_parent_scope')
    target=private/name
    if target.is_symlink() or target.resolve()!=target:fail('acquire_parent_path')
    prior=safe_json(target/'result.json',32*1024*1024);reservation=safe_json(target/'reservation.json',65536)
    parent=prior.get('match_retained',{}).get('data',{})
    digest=hashlib.sha256(json.dumps(parent,sort_keys=True,separators=(',',':')).encode()).hexdigest()
    if (prior.get('status')!='complete' or prior.get('mode')!='match-retained-current'
            or parent.get('state')!='current_retained_read_only' or prior.get('production_unchanged') is not True
            or any(x.get('operation_id')!=name for x in (prior,reservation,parent))
            or parent.get('source_sha')!=prior.get('source_sha') or prior.get('source_sha')!=reservation.get('source_sha')
            or digest!=(continuation['parent_evidence_sha256'] if continuation else 'c0d7c1f53cceea267f08a5b15d29d9f2bb314401b7de380e0fe40b59ad513046')
            or digest!=prior.get('match_retained',{}).get('evidence_sha256')
            or any(type(x.get(k)) is not int or x[k]!=0 for x in (prior,parent) for k in ('supplier_calls','database_writes','mapping_writes'))):fail('acquire_parent_not_complete')
    raw=target/'current-raw.json'
    if not safe_file(raw,32*1024*1024) or hashlib.sha256(raw.read_bytes()).hexdigest()!=parent.get('current_raw_sha256'):fail('acquire_parent_raw')
    before=hashlib.sha256((target/'result.json').read_bytes()).hexdigest()
    if continuation:
        if before!=continuation['parent_receipt_sha256']:fail('continuation_parent_receipt')
        prior_receipt=retained_prior_selection(continuation)
    previous=None
    if continuation and continuation.get('scope_kind')=='owner_date_variation':
        checked=safe_json(private/continuation['readback_operation_id']/'result.json',1048576)
        checked_reservation=safe_json(private/continuation['readback_operation_id']/'reservation.json',65536)
        inspected=checked.get('match_retained',{}).get('data',{})
        if (checked.get('status')!='complete' or checked.get('mode')!='match-retained-readback'
                or checked.get('production_unchanged') is not True
                or any(x.get('operation_id')!=continuation['readback_operation_id'] for x in (checked,checked_reservation,inspected))
                or any(x.get('source_sha')!=checked_reservation.get('source_sha') for x in (checked,inspected))
                or inspected.get('target_operation_id')!=continuation['saved_acquisition_operation_id']
                or checked['match_retained'].get('evidence_sha256')!=hashlib.sha256(json.dumps(inspected,sort_keys=True,separators=(',',':')).encode()).hexdigest()
                or inspected.get('state')!='saved_detail_inspected_read_only' or inspected.get('continuation_scope_sha256')!=continuation.get('saved_inspection_scope_sha256',NEXT_SCOPE['sha256'])
                or any(type(x.get(k)) is not int or x[k]!=0 for x in (checked,inspected) for k in ('supplier_calls','database_writes','mapping_writes'))):fail('saved_detail_inspection_required')
        previous=retained_effective_requests(continuation)
    if continuation and continuation.get('scope_kind')=='never_selected_bounded':
        previous=retained_effective_requests(continuation)
    catalogue_before=None
    if 'catalogue_sha256' in NEXT_SCOPE:
        catalogue_before,previous=retained_catalogue_history(continuation,stage)
    config=project/'config.php'
    if not safe_file(config,1048576):fail('acquire_config_path')
    config_hash=hashlib.sha256(config.read_bytes()).hexdigest()
    fd=os.open(op/'acquisition-input.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:handle.write(json.dumps(dict(current=parent,parent_evidence_sha256=digest,**({'continuation_scope':continuation,'continuation_scope_sha256':NEXT_SCOPE['sha256'],'continuation_scope_path':NEXT_SCOPE['path']} if continuation else {}),**({'continuation_catalogue_sha256':NEXT_SCOPE['catalogue_sha256']} if catalogue_before is not None else {}),**({'prior_effective_requests':previous} if previous is not None else {})),sort_keys=True).encode());handle.flush();os.fsync(handle.fileno())
    env={k:os.environ[k] for k in ('HOME','PATH','LANG','LC_ALL') if k in os.environ}
    env.update(MATCH_RETAINED_DIR=str(op),MATCH_RETAINED_OPERATION=operation)
    run=subprocess.run(['python3',str(stage/'scripts/diagnostics/hotel_match_retained_acquire_v1.py')],cwd=project,env=env,capture_output=True,text=True,timeout=780)
    if run.returncode or run.stderr.strip() or len(run.stdout.encode())>4*1024*1024:
        result['match_retained_child_failure']={'exit_code':run.returncode,'stdout_sha256':hashlib.sha256(run.stdout.encode()).hexdigest(),'stderr_sha256':hashlib.sha256(run.stderr.encode()).hexdigest()}
        fail('acquire_child_failed_no_replay')
    data=json.loads(run.stdout)
    if (data.get('state')!='acquired_retained_native_read_only' or data.get('selected_hotels')!=(continuation['selected_count'] if continuation else 105)
            or data.get('parent_evidence_sha256')!=digest
            or data.get('selected_id_sha256')!=(hashlib.sha256(json.dumps(continuation['selected_tv_hotel_ids'],separators=(',',':')).encode()).hexdigest() if continuation else '4f8fa702fad1445d2a6bb5978bbdc1e60f30abd75b9328251f381a86f2b351d3')
            or data.get('safe_to_write_now') is not False
            or any(type(data.get(k)) is not int or data[k]!=0 for k in ('database_writes','mapping_writes','new_accepted_mappings'))
            or type(data.get('supplier_calls')) is not int or not 0<=data['supplier_calls']<=(continuation.get('supplier_http_maximum',400) if continuation else 400)
            or hashlib.sha256((target/'result.json').read_bytes()).hexdigest()!=before
            or hashlib.sha256(config.read_bytes()).hexdigest()!=config_hash):fail('acquire_result_binding')
    if continuation and retained_prior_selection(continuation)!=prior_receipt:fail('acquire_history_drift_unknown_no_replay')
    checked_previous=retained_effective_requests(continuation) if previous is not None else None
    if catalogue_before is not None:
        catalogue_after,checked_previous=retained_catalogue_history(continuation,stage)
        if catalogue_after!=catalogue_before or data.get('continuation_catalogue_sha256')!=NEXT_SCOPE['catalogue_sha256']:fail('catalogue_result_binding_unknown_no_replay')
        data['catalogue_sibling_history_sha256']=hashlib.sha256(json.dumps(catalogue_before,sort_keys=True,separators=(',',':')).encode()).hexdigest()
    if previous is not None and (checked_previous!=previous
            or type(data.get('tariff_search_units')) is not int or not 0<=data['tariff_search_units']<=1
            or data.get('physical_http_reservations')!=data['supplier_calls']):fail('context_result_binding_unknown_no_replay')
    if continuation and continuation.get('scope_kind')=='never_selected_bounded' and (
            data.get('context_origin')!=continuation['context_origin'] or data.get('wire_context')!=continuation['wire_context']
            or not re.fullmatch(r'[a-f0-9]{64}',str(data.get('selection_reservation_sha256','')))):
        fail('new_selection_result_binding_unknown_no_replay')
    data.update(operation_id=operation,source_sha=source,control_source_sha=payload['match_retained_control_sha'],
        cohort=payload['cohort'],target_operation_id=name,parent_receipt_sha256=before,no_replay=True)
    evidence=json.dumps(data,sort_keys=True,separators=(',',':')).encode()
    fd=os.open(op/'retained-evidence.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:handle.write(evidence);handle.flush();os.fsync(handle.fileno())
    return {'evidence_sha256':hashlib.sha256(evidence).hexdigest(),'data':data}

def owner_top500_current_projection(data, profile):
    # Raw helper rows remain in the private operation directory. Return only
    # fixed expected-pair booleans: no other native IDs, anchors or trip context.
    captured=data.get('captured_at_utc')
    if (not isinstance(captured,str) or not re.fullmatch(r'\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(?:Z|\+00:00)',captured)
            or data.get('database_snapshot')!='repeatable-read-read-only'):fail('owner_top500_current_snapshot')
    by_tv={r['tv_hotel_id']:r for r in data['rows']};rows=[]
    for anex,tv,rank in profile['pairs']:
        raw=by_tv[tv];target=raw.get('target');native=raw.get('direct_anex_native_ids')
        if (not isinstance(native,list) or any(type(n) is not int or n<1 for n in native)
                or (target is not None and (not isinstance(target,dict) or str(target.get('id'))!=str(tv)
                    or str(target.get('is_active')) not in ('0','1')))):fail('owner_top500_current_row')
        present=target is not None;active=present and str(target['is_active'])=='1'
        if ((not present)!=(raw.get('state')=='target_missing')
                or (present and not active and raw.get('state')!='target_ineligible')):fail('owner_top500_current_target')
        eligible=present and active and raw.get('state')!='target_ineligible'
        effective=anex in native
        if eligible and effective and raw.get('state')!='already_effectively_linked':fail('owner_top500_current_effective')
        state='target_missing' if not present else 'target_ineligible' if not eligible else 'matched_current' if effective else 'expected_pair_unresolved'
        rows.append({'anex_hotel_id':anex,'tv_hotel_id':tv,'owner_rank':rank,'state':state,
                     'effective_pair_present':effective,'target_present':present,
                     'target_active':active,'target_eligible':eligible})
    return {'schema_version':1,'state':'current_retained_read_only','captured_at_utc':captured,
            'database_snapshot':'repeatable-read-read-only','parent_receipt_sha256':data['parent_receipt_sha256'],
            'cohort_hotels':len(rows),'matched_current_pairs':sum(r['state']=='matched_current' for r in rows),'rows':rows,
            'publication_admission':False,'website_online_bridge_verified':False,
            'canonical_publication_binding_verified':False,'supplier_calls':0,'database_writes':0,
            'mapping_writes':0,'safe_to_write_now':False}

def current_retained_target(stage):
    profile=globals().get('OWNER_TOP500_CURRENT',{})
    bounded=(payload.get('cohort')==profile.get('cohort') or operation==profile.get('operation_id'))
    name=payload.get('target_operation_id','')
    if bounded and (globals().get('mode')!='match-retained-current'
                    or operation!=profile['operation_id'] or payload.get('cohort')!=profile['cohort']
                    or name!=profile['parent_operation_id']):fail('owner_top500_current_scope')
    if not re.fullmatch(r'int-andromeda-match-retained-project-[0-9]{8}-v[1-9][0-9]*',name):fail('current_parent_name')
    target=private/name
    if target.is_symlink() or target.resolve()!=target:fail('current_parent_path')
    prior=safe_json(target/'result.json',32*1024*1024);reservation=safe_json(target/'reservation.json',65536)
    parent=prior.get('match_retained',{}).get('data',{});projection=parent.get('projection',{})
    if (prior.get('status')!='complete' or prior.get('mode')!='match-retained-project'
            or parent.get('state')!='reprojected_retained_read_only'
            or projection.get('state')!='projected_read_only' or projection.get('cohort')!='common4-retained'
            or prior.get('production_unchanged') is not True
            or any(x.get('operation_id')!=name for x in (prior,reservation,parent))
            or prior.get('source_sha')!=reservation.get('source_sha') or parent.get('source_sha')!=prior.get('source_sha')
            or hashlib.sha256(json.dumps(parent,sort_keys=True,separators=(',',':')).encode()).hexdigest()!=prior.get('match_retained',{}).get('evidence_sha256')
            or any(type(x.get(k)) is not int or x[k]!=0 for x in (prior,parent,projection) for k in ('supplier_calls','database_writes','mapping_writes'))):
        fail('current_parent_not_complete')
    ids=projection.get('cohort_tv_hotel_ids')
    if (not isinstance(ids,list) or not 0<len(ids)<=10000 or any(type(v) is not int or v<1 for v in ids)
            or ids!=sorted(set(ids)) or len(ids)!=projection.get('cohort_hotels')):fail('current_parent_cohort')
    parent_hash=hashlib.sha256((target/'result.json').read_bytes()).hexdigest()
    if bounded:
        if parent_hash!=profile['parent_receipt_sha256']:fail('owner_top500_current_parent_pin')
        selected=sorted(p[1] for p in profile['pairs'])
        if not set(selected).issubset(ids):fail('owner_top500_current_parent_membership')
        ids=selected
    scope={'tv_hotel_ids':ids,'parent_receipt_sha256':parent_hash}
    fd=os.open(op/'current-scope.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:handle.write(json.dumps(scope,sort_keys=True).encode());handle.flush();os.fsync(handle.fileno())
    config=project/'config.php'
    if not safe_file(config,1048576):fail('current_config_path')
    before_hash=hashlib.sha256(config.read_bytes()).hexdigest()
    env={k:os.environ[k] for k in ('HOME','PATH','LANG','LC_ALL') if k in os.environ}
    env.update(MATCH_RETAINED_DIR=str(op),MATCH_RETAINED_SOURCE_ROOT=str(stage))
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',
        '-d','memory_limit=512M','-d','disable_functions='+disabled,
        str(stage/'scripts/diagnostics/hotel_match_retained_current_v1.php')],cwd=project,env=env,capture_output=True,text=True,timeout=300)
    if len(run.stdout.encode())>32*1024*1024:fail('current_output_bound')
    fd=os.open(op/'current-raw.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:handle.write(run.stdout.encode());handle.flush();os.fsync(handle.fileno())
    if run.returncode or run.stderr.strip():
        result['match_retained_child_failure']={'exit_code':run.returncode,'stdout_sha256':hashlib.sha256(run.stdout.encode()).hexdigest(),'stderr_sha256':hashlib.sha256(run.stderr.encode()).hexdigest()}
        fail('current_child_failed_no_replay')
    data=json.loads(run.stdout)
    if (data.get('state')!='current_retained_read_only' or data.get('parent_receipt_sha256')!=parent_hash
            or data.get('cohort_hotels')!=len(ids) or [r.get('tv_hotel_id') for r in data.get('rows',[])]!=ids
            or data.get('safe_to_write_now') is not False
            or any(type(data.get(k)) is not int or data[k]!=0 for k in ('supplier_calls','database_writes','mapping_writes'))
            or hashlib.sha256((target/'result.json').read_bytes()).hexdigest()!=parent_hash
            or hashlib.sha256(config.read_bytes()).hexdigest()!=before_hash):fail('current_result_binding')
    if bounded:data=owner_top500_current_projection(data,profile)
    data.update(operation_id=operation,source_sha=source,control_source_sha=payload['match_retained_control_sha'],
        cohort=payload['cohort'],target_operation_id=name,current_raw_sha256=hashlib.sha256(run.stdout.encode()).hexdigest(),
        no_replay=True,new_database_reads=1)
    raw_result=json.dumps(data,sort_keys=True,separators=(',',':')).encode()
    fd=os.open(op/'retained-evidence.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:handle.write(raw_result);handle.flush();os.fsync(handle.fileno())
    return {'evidence_sha256':hashlib.sha256(raw_result).hexdigest(),'data':data}

def project_retained_target(stage):
    target_name=payload.get('target_operation_id','')
    if not re.fullmatch(r'int-andromeda-match-retained-[0-9]{8}-v[1-9][0-9]*',target_name):fail('retained_projection_target')
    target=private/target_name
    if target.is_symlink() or target.resolve()!=target:fail('retained_projection_target_path')
    prior=safe_json(target/'result.json',32*1024*1024);reservation=safe_json(target/'reservation.json',65536)
    parent=prior.get('match_retained',{}).get('data',{});capture=parent.get('capture',{})
    if (prior.get('status')!='complete' or prior.get('mode')!='match-retained-intake'
            or prior.get('production_unchanged') is not True
            or prior.get('operation_id')!=target_name or reservation.get('operation_id')!=target_name
            or parent.get('operation_id')!=target_name or parent.get('source_sha')!=reservation.get('source_sha')
            or capture.get('state')!='captured_read_only'
            or hashlib.sha256(json.dumps(parent,sort_keys=True,separators=(',',':')).encode()).hexdigest()!=prior.get('match_retained',{}).get('evidence_sha256')
            or any(type(prior.get(k)) is not int or prior[k]!=0 for k in ('supplier_calls','database_writes','mapping_writes'))):
        fail('retained_projection_parent_not_complete')
    raw=target/'retained-raw.ndjson'
    if not safe_file(raw,134217728) or raw.stat().st_nlink!=1:fail('retained_projection_raw')
    receipt_before=hashlib.sha256((target/'result.json').read_bytes()).hexdigest()
    env={k:os.environ[k] for k in ('HOME','PATH','LANG','LC_ALL') if k in os.environ}
    run=subprocess.run(['python3',str(stage/'scripts/diagnostics/hotel_match_retained_projection_v1.py'),str(raw)],cwd=project,env=env,capture_output=True,text=True,timeout=240)
    if run.returncode or run.stderr.strip() or len(run.stdout.encode())>32*1024*1024:fail('retained_saved_projection_failed')
    projected=json.loads(run.stdout)
    if (projected.get('state')!='projected_read_only' or projected.get('raw_sha256')!=capture.get('raw_sha256')
            or projected.get('raw_bytes')!=capture.get('raw_bytes')
            or projected.get('safe_to_write_now') is not False
            or any(projected.get('capture_rows',{}).get(k,0)!=capture.get('rows',{}).get(k) for k in ('observation','legacy_source','identity','target'))
            or hashlib.sha256((target/'result.json').read_bytes()).hexdigest()!=receipt_before):
        fail('retained_saved_projection_binding')
    data={'state':'reprojected_retained_read_only','target_operation_id':target_name,
        'parent_source_sha':reservation['source_sha'],'parent_receipt_sha256':receipt_before,
        'projection':projected,'operation_id':operation,'source_sha':source,
        'control_source_sha':payload['match_retained_control_sha'],'cohort':payload['cohort'],
        'supplier_calls':0,'database_writes':0,'mapping_writes':0,'safe_to_write_now':False,'no_replay':True}
    raw_result=json.dumps(data,sort_keys=True,separators=(',',':')).encode()
    fd=os.open(op/'retained-evidence.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:handle.write(raw_result);handle.flush();os.fsync(handle.fileno())
    return {'evidence_sha256':hashlib.sha256(raw_result).hexdigest(),'data':data}

def retained_reference_projection(raw,remaining,operator_family,expected_kinds=None):
    import collections,io
    counts=collections.Counter();fields={k:collections.Counter() for k in ('tour_id','search_id','source')}
    candidates=[];schema=None;kinds=collections.Counter();observed=set()
    def field(row,key):
        if key not in row:return 'field_missing'
        value=row[key]
        if value is None:return 'null'
        if isinstance(value,str) and not value.strip():return 'empty'
        if isinstance(value,(str,int)) and type(value) is not bool:
            if len(str(value))>4096:return 'too_long'
            return 'positive_decimal' if re.fullmatch(r'[1-9][0-9]{0,21}',str(value)) else 'opaque_scalar'
        return 'unsupported_type'
    for number,line in enumerate(io.BytesIO(raw),1):
        if number>400001 or len(line)>4194304:fail('proof_capture_row_bound')
        item=json.loads(line);kind=item.get('kind');kinds[kind]+=1
        if kind not in ('scope','observation','legacy_source','identity','target'):fail('proof_capture_kind')
        if kind=='scope':
            if schema is not None or number!=1 or item.get('cohort')!='common4-retained' or item.get('catalog_id_namespace')!='tourvisor_hotel':fail('proof_capture_scope')
            schema=item.get('schema_columns',{}).get('tour_operator_identity_observations')
            if not isinstance(schema,list) or any(not isinstance(x,str) for x in schema):fail('proof_capture_schema')
        if kind!='observation':continue
        row=item.get('row');hid=row.get('hotel_id') if isinstance(row,dict) else None
        if not re.fullmatch(r'[1-9][0-9]{0,21}',str(hid)):fail('proof_observation_hotel')
        hid=int(hid)
        if hid not in remaining:continue
        counts['remaining_observation_rows']+=1;observed.add(hid)
        states={k:field(row,k) for k in fields}
        for key,state in states.items():fields[key][state]+=1
        useful=any(states[k] in ('positive_decimal','opaque_scalar') for k in ('tour_id','search_id'))
        if not useful:continue
        family=operator_family(row.get('operator_name'));family=family if family in ('ANEX','FUN&SUN','Библио-Глобус','Интурист') else 'unresolved'
        operator=row.get('operator_id');operator=int(operator) if re.fullmatch(r'[1-9][0-9]{0,21}',str(operator)) else None
        counts['reference_candidate_rows']+=1;counts['family_'+family]+=1
        country=row.get('country_id');country=int(country) if re.fullmatch(r'[1-9][0-9]{0,21}',str(country)) else None
        target_country=(remaining[hid].get('target') or {}).get('country_id')
        target_country=int(target_country) if re.fullmatch(r'[1-9][0-9]{0,21}',str(target_country)) else None
        unknown_country=country is None or target_country is None
        conflict=not unknown_country and country!=target_country
        counts['country_disagreements']+=int(conflict)
        counts['country_comparison_unknown']+=int(unknown_country)
        candidates.append({'tv_hotel_id':hid,'observed_operator_id':operator,'operator_family':family,
            'country_disagreement':conflict,'field_states':states,'line_number':number,
            'line_sha256':hashlib.sha256(line).hexdigest(),
            'reference_hashes':{k:hashlib.sha256(json.dumps(row.get(k),sort_keys=True,separators=(',',':')).encode()).hexdigest() for k in fields if k in row},
            'private_fields':{k:row[k] for k in ('id','operator_name','tour_id','search_id','source','first_seen_at','last_seen_at') if k in row and len(json.dumps(row[k]))<=8192}})
    if expected_kinds is None:expected_kinds=dict(scope=1,observation=13611,legacy_source=14687,identity=15298,target=5310)
    if schema is None or kinds!=expected_kinds:fail('proof_capture_counts')
    summary={'schema_fields_present':{k:k in schema for k in fields},'field_states':{k:dict(v) for k,v in fields.items()},
        'counts':dict(counts),'remaining_targets_with_observations':len(observed),'candidate_tv_hotel_ids':sorted({r['tv_hotel_id'] for r in candidates}),
        'native_authority':False,'account_binding_authority':False}
    return summary,candidates

def retained_result_structure(value,shape,selected):
    import collections
    def keys(v):return sorted(k for k in v if isinstance(k,str) and re.fullmatch(r'[A-Za-z_][A-Za-z_0-9]{0,63}',k) and not re.search(r'token|password|secret|authorization|url|link',k,re.I))[:60] if isinstance(v,dict) else []
    unwrapped=shape.unwrap(value);out={'root_type':type(value).__name__,'root_keys':keys(value),'unwrapped_type':type(unwrapped).__name__,'unwrapped_keys':keys(unwrapped)}
    for name,obj in (('root',value),('unwrapped',unwrapped)):
        if isinstance(obj,dict):out[name+'_pagination_numeric']={k:v for k,v in obj.items() if k in ('total','totalHotels','hotelCount','page','limit','progress','hasMore','hasNext') and type(v) in (int,bool) and (type(v) is bool or 0<=v<=10000000)}
    try:rows=shape.hotel_rows(value)
    except (RuntimeError,TypeError,AttributeError):return dict(out,shape_state='unrecognized_envelope',native_authority=False)
    counts=collections.Counter();operators=collections.Counter();chosen=set()
    for hotel in rows:
        counts['raw_hotel_rows']+=1
        if not isinstance(hotel,dict):counts['nonobject_hotel_rows']+=1;continue
        hid=shape.num(hotel.get('id'));is_selected=hid in selected
        if is_selected:counts['selected_hotel_rows']+=1;chosen.add(hid)
        if any(k in hotel for k in ('hotelId','hotel_id','hotel')):counts['hotel_alias_presence_rows']+=1
        tours=hotel.get('tours')
        if not isinstance(tours,list):counts['nonlist_tours_rows']+=1;continue
        for tour in tours:
            counts['raw_tour_rows']+=1
            if not isinstance(tour,dict):counts['nonobject_tour_rows']+=1;continue
            operator=shape.ident(tour,'operator');operators[str(operator) if operator is not None else 'unresolved']+=1
            if 'tour_id' in tour:counts['alternate_tour_id_presence']+=1
            tid=shape.num(tour.get('id') or tour.get('tourId'))
            if tid is None:counts['missing_or_nonnumeric_tour_ids']+=1
            if is_selected and operator==13:
                counts['selected_operator13_tours']+=1
                counts['selected_operator13_numeric_tour_ids' if tid is not None else 'selected_operator13_unusable_tour_ids']+=1
    out.update(shape_state='recognized_empty' if not rows else 'recognized_nonempty',counts=dict(counts),operator_id_counts=dict(operators),selected_hotel_ids=sorted(chosen),native_authority=False)
    return out

def retained_proof_sources(stage):
    import pathlib,sys
    profile=PROOF_SOURCE_INSPECTION;protected={}
    if operation!=profile['operation_id'] or payload.get('target_operation_id')!=profile['target_operation_id'] or payload.get('cohort')!=profile['cohort']:fail('proof_source_inspection_scope')
    def read(path,bound):
        if not safe_file(path,bound) or path.resolve()!=path or path.stat().st_nlink!=1:fail('proof_source_path')
        before=path.stat();raw=path.read_bytes();after=path.stat()
        stamp=lambda s:(s.st_dev,s.st_ino,s.st_size,s.st_mtime_ns,s.st_ctime_ns)
        if stamp(before)!=stamp(after) or len(raw)!=after.st_size:fail('proof_source_drift')
        protected[path]=(hashlib.sha256(raw).hexdigest(),stamp(after));return raw
    def receipt(name,digest,bound=32*1024*1024):
        target=private/name
        if target.is_symlink() or target.resolve()!=target:fail('proof_source_directory')
        raw=read(target/'result.json',bound)
        if hashlib.sha256(raw).hexdigest()!=digest:fail('proof_source_receipt')
        wrapper=json.loads(raw);reservation=json.loads(read(target/'reservation.json',65536))
        data=wrapper.get('match_retained',{}).get('data',{})
        if (wrapper.get('status')!='complete' or wrapper.get('production_unchanged') is not True
                or wrapper.get('operation_id')!=name or reservation.get('operation_id')!=name
                or wrapper.get('source_sha')!=reservation.get('source_sha')
                or any(type(x.get(k)) is not int or x[k]!=0 for x in (wrapper,data) for k in ('database_writes','mapping_writes'))):fail('proof_source_binding')
        return target,wrapper,data,reservation
    capture,capture_wrapper,capture_data,_=receipt(profile['target_operation_id'],profile['capture_receipt_sha256'])
    if (capture_wrapper.get('mode')!='match-retained-intake' or capture_data.get('state')!='captured_and_projected_read_only'
            or capture_data.get('capture',{}).get('raw_sha256')!=profile['capture_raw_sha256']
            or capture_data.get('capture',{}).get('raw_bytes')!=profile['capture_raw_bytes']):fail('proof_capture_receipt_binding')
    raw=read(capture/'retained-raw.ndjson',134217728)
    if len(raw)!=profile['capture_raw_bytes'] or hashlib.sha256(raw).hexdigest()!=profile['capture_raw_sha256']:fail('proof_capture_raw_binding')
    _,current_wrapper,current,_=receipt(profile['current_operation_id'],profile['current_receipt_sha256'])
    if (current_wrapper.get('mode')!='match-retained-current' or current.get('state')!='current_retained_read_only'
            or hashlib.sha256(json.dumps(current,sort_keys=True,separators=(',',':')).encode()).hexdigest()!=profile['current_evidence_sha256']):fail('proof_current_binding')
    acquired,wrapper,data,reservation=receipt(profile['acquisition_operation_id'],profile['acquisition_receipt_sha256'])
    if (wrapper.get('mode')!='match-retained-acquire' or wrapper.get('source_sha')!=profile['acquisition_source_sha']
            or data.get('source_sha')!=profile['acquisition_source_sha']
            or data.get('acquisition_state')!='completed' or data.get('unknown_http_calls')!=0
            or data.get('target_operation_id')!=profile['current_operation_id']
            or data.get('parent_receipt_sha256')!=profile['current_receipt_sha256']
            or data.get('parent_evidence_sha256')!=profile['current_evidence_sha256']
            or data.get('continuation_catalogue_sha256')!=profile['catalogue_sha256']
            or hashlib.sha256(json.dumps(data,sort_keys=True,separators=(',',':')).encode()).hexdigest()!=profile['acquisition_evidence_sha256']
            or not retained_completed_bounded_batch(data,2)):fail('proof_acquisition_binding')
    catalogue_raw=read(acquired/'source/scripts/deploy/fixtures/hotel_match_retained_scope_catalogue_v1.json',1048576)
    if hashlib.sha256(catalogue_raw).hexdigest()!=profile['catalogue_sha256']:fail('proof_catalogue_binding')
    catalogue=json.loads(catalogue_raw);members=catalogue.get('scopes',[])
    if catalogue.get('schema_version')!=1 or catalogue.get('kind')!='retained_never_selected_catalogue' or len(members)!=2:fail('proof_catalogue_shape')
    selected=set(members[0]['prior_selected_hotel_ids'])|{345,131024}
    if len(selected)!=206 or hashlib.sha256(json.dumps(sorted(selected),separators=(',',':')).encode()).hexdigest()!=profile['selected_id_sha256']:fail('proof_selected_binding')
    ready={r['tv_hotel_id']:r for r in current.get('rows',[]) if r.get('state')=='missing_direct_anex_with_future_context_and_anchor' and r.get('acquisition_ready') is True and r.get('anchor_state')=='canonical_anchor_ok' and not r.get('direct_anex_native_ids')}
    remaining={h:r for h,r in ready.items() if h not in selected}
    if (len(ready)!=606 or len(set(ready)&selected)!=199 or len(remaining)!=407
            or hashlib.sha256(json.dumps(sorted(remaining),separators=(',',':')).encode()).hexdigest()!=profile['remaining_id_sha256']):fail('proof_remaining_binding')
    home=pathlib.Path.home()/'.anytoour-match'
    ledgers={'hotel_selection':home/'retained-selection-claims.json','context_selection':home/'retained-context-claims.json','account_day':home/'provider-quotas/tourvisor-anex-2026-10-20.json'}
    ledger_hashes={k:hashlib.sha256(read(p,8*1024*1024)).hexdigest() for k,p in ledgers.items()}
    if ledger_hashes['hotel_selection']!=profile['hotel_ledger_sha256'] or ledger_hashes['context_selection']!=profile['context_ledger_sha256']:fail('proof_ledger_binding')
    sys.path.insert(0,str(stage/'scripts/diagnostics'))
    import hotel_match_retained_projection_v1 as projection
    import hotel_match_live30_common4_continuation_acquire_v10 as shape
    refs,candidates=retained_reference_projection(raw,remaining,projection.operator_family)
    start=json.loads(read(acquired/'request-0001.json',65536));status=json.loads(read(acquired/'request-0002.json',65536))
    request=json.loads(read(acquired/'request-0003.json',65536));meta=json.loads(read(acquired/'response-0003.json',65536))
    member=next((x for x in members if x.get('operation_id')==profile['acquisition_operation_id']),None)
    if (member is None or member.get('selected_tv_hotel_ids')!=[345,131024]
            or data.get('continuation_scope_sha256')!=hashlib.sha256(json.dumps(member,sort_keys=True,separators=(',',':')).encode()).hexdigest()
            or start.get('action')!='search_start' or start.get('path')!='/tours/search'
            or start.get('params')!={'departureId':10,'countryId':1,'dateFrom':'2026-10-30','dateTo':'2026-10-30','nightsFrom':6,'nightsTo':6,'adults':2,'childs':[],'currency':'RUB','onlyCharter':False,'operatorIds':[13],'hotelIds':[345,131024]}
            or status.get('action')!='search_status' or status.get('path')!=str(request.get('path'))+'/status'
            or status.get('params')!={'operatorStatus':False}):fail('proof_request_binding')
    if (request.get('action')!='search_results' or request.get('params')!={'limit':10000}
            or not re.fullmatch(r'/tours/search/[1-9][0-9]{0,21}',str(request.get('path','')))
            or meta.get('http_status')!=200 or meta.get('complete_body') is not True):fail('proof_response_binding')
    result_raw=read(acquired/'response-0003.bin',16*1024*1024)
    if hashlib.sha256(result_raw).hexdigest()!=meta.get('raw_sha256'):fail('proof_response_digest')
    structure=retained_result_structure(json.loads(result_raw),shape,{345,131024})
    private_value={'references':candidates,'native_authority':False,'source_capture_sha256':profile['capture_raw_sha256']}
    encoded=json.dumps(private_value,sort_keys=True,separators=(',',':')).encode()
    if len(encoded)>32*1024*1024:fail('proof_private_output_bound')
    fd=os.open(op/'proof-source-candidates.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:handle.write(encoded);handle.flush();os.fsync(handle.fileno())
    for path,(digest,stamp) in protected.items():
        stat=path.stat()
        if ((stat.st_dev,stat.st_ino,stat.st_size,stat.st_mtime_ns,stat.st_ctime_ns)!=stamp
                or hashlib.sha256(path.read_bytes()).hexdigest()!=digest):fail('proof_protected_input_drift')
    return {'state':'retained_proof_sources_inspected_read_only','target_operation_id':profile['target_operation_id'],
        'capture_raw_sha256':profile['capture_raw_sha256'],'capture_receipt_sha256':profile['capture_receipt_sha256'],
        'current_evidence_sha256':profile['current_evidence_sha256'],'acquisition_receipt_sha256':profile['acquisition_receipt_sha256'],
        'acquisition_result_raw_sha256':meta['raw_sha256'],'reference_projection':refs,'result_structure':structure,
        'same_parent_ready':606,'same_parent_selected_ready':199,'same_parent_never_selected':407,
        'remaining_id_sha256':profile['remaining_id_sha256'],'private_reference_projection_sha256':hashlib.sha256(encoded).hexdigest(),
        'protected_inputs_count':len(protected),'protected_inputs_unchanged':True,'protected_ledger_sha256':ledger_hashes,
        'new_accepted_mappings':0,'native_authority':False,'global_usage':'not_measured'}

def retained_readback():
    target_name=payload.get('target_operation_id','')
    if not re.fullmatch(r'int-andromeda-match-(?:retained|quota5000)-[0-9]{8}-v[1-9][0-9]*',target_name):fail('retained_readback_target')
    target=private/target_name
    if target.is_symlink() or target.resolve()!=target:fail('retained_target_path')
    reservation=safe_json(target/'reservation.json',65536);prior=safe_json(target/'result.json',32*1024*1024)
    if reservation.get('operation_id')!=target_name or prior.get('operation_id')!=target_name:fail('retained_target_identity')
    out={'target_operation_id':target_name,'target_source_sha':reservation.get('source_sha'),
        'prior_status':prior.get('status'),'prior_reason':prior.get('reason'),
        'prior_supplier_calls':prior.get('supplier_calls'),'prior_database_writes':prior.get('database_writes'),
        'prior_receipt_sha256':hashlib.sha256((target/'result.json').read_bytes()).hexdigest()}
    raw=target/'retained-raw.ndjson';out['raw_present']=raw.exists()
    if raw.exists():
        if not safe_file(raw,134217728) or raw.stat().st_nlink!=1:fail('retained_target_raw_path')
        digest=hashlib.sha256();kinds={};scope=None
        with raw.open('rb') as handle:
            for line in handle:
                if len(line)>4194304:fail('retained_target_raw_bound')
                digest.update(line)
                try:r=json.loads(line)
                except (ValueError,TypeError):kinds['invalid_json']=kinds.get('invalid_json',0)+1;continue
                kind=r.get('kind')
                if kind not in ('scope','observation','legacy_source','identity','target'):kind='unknown_kind'
                kinds[kind]=kinds.get(kind,0)+1
                if kind=='scope':scope={k:r.get(k) for k in ('cohort','captured_at_utc','database_snapshot','schema_columns')}
        out.update(raw_bytes=raw.stat().st_size,raw_sha256=digest.hexdigest(),raw_row_kinds=kinds,captured_scope=scope)
    helper=project/'data/db-v1.php';out['db_helper_sha256']=hashlib.sha256(helper.read_bytes()).hexdigest()
    if out['db_helper_sha256']=='ca7f11d6ec53e0cd3d4464e0f645f15b761bb734da1323689479838868d4abc4':
        php=r"""$_SERVER['DOCUMENT_ROOT']=$argv[1];require $argv[1].'/data/db-v1.php';try{$db=v2_data_db();$db->exec('SET TRANSACTION READ ONLY');$db->beginTransaction();$out=[];foreach(['tour_operator_identity_observations','local_tv_legacy_links','andromeda_hotel_identities','catalog_hotels']as $table){$q=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute([$table]);$out[$table]=$q->fetchAll(PDO::FETCH_COLUMN);}$db->commit();echo json_encode(['columns'=>$out],JSON_THROW_ON_ERROR);}catch(Throwable $e){echo json_encode(['error_class'=>get_class($e),'error_sha256'=>hash('sha256',$e->getMessage())]);exit(1);}"""
        env={k:os.environ[k] for k in ('HOME','PATH','LANG','LC_ALL') if k in os.environ}
        run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0','-r',php,str(project)],env=env,cwd=project,capture_output=True,text=True,timeout=30)
        if run.stderr.strip() or len(run.stdout)>65536:fail('retained_schema_readback_failed')
        out['database_schema']=json.loads(run.stdout);out['schema_readback_exit']=run.returncode
    out['state']='reconciled_read_only'
    return out

def run_match_retained(stage):
    owner_current=(mode=='match-retained-current' and payload.get('cohort')==globals().get('OWNER_TOP500_CURRENT',{}).get('cohort'))
    if ((payload.get('cohort')!=RETAINED_MODES.get(mode) and not owner_current and not (payload.get('cohort')==NEXT_SCOPE['cohort'] and mode in ('match-retained-acquire','match-retained-anex-write-v2','match-retained-readback')))
            or not re.fullmatch(r'[a-f0-9]{40}',str(payload.get('match_retained_control_sha','')))):
        fail('retained_remote_scope')
    if mode=='match-retained-project':return project_retained_target(stage)
    if mode=='match-retained-current':return current_retained_target(stage)
    if mode=='match-retained-acquire':return acquire_retained_target(stage)
    if mode=='match-retained-anex-write':fail('write_v1_terminal_no_replay')
    if mode=='match-retained-anex-write-v2':return write_retained_anex_target(stage)
    if mode=='match-retained-readback' and operation==PROOF_SOURCE_INSPECTION['operation_id']:
        # No generic schema/PHP readback or dependency on a foreign installed DB helper.
        # The stock outer source, owner, slot, reservation and production guards still apply.
        data=retained_proof_sources(stage)
        data.update(operation_id=operation,source_sha=source,control_source_sha=payload['match_retained_control_sha'],
            cohort=payload['cohort'],supplier_calls=0,database_writes=0,mapping_writes=0,safe_to_write_now=False,no_replay=True)
        raw_result=json.dumps(data,sort_keys=True,separators=(',',':')).encode()
        return {'evidence_sha256':hashlib.sha256(raw_result).hexdigest(),'data':data}
    config=project/'config.php';dbhelper=project/'data/db-v1.php'
    if not safe_file(config,1048576) or not safe_file(dbhelper,65536):fail('retained_config_paths')
    config_hash=hashlib.sha256(config.read_bytes()).hexdigest()
    if mode=='match-retained-readback':
        data=retained_transport_diagnostic(stage) if operation==TRANSPORT_DIAGNOSTIC['operation_id'] else retained_saved_detail(stage) if payload.get('cohort')==NEXT_SCOPE['cohort'] else retained_readback()
        data.update(operation_id=operation,source_sha=source,control_source_sha=payload['match_retained_control_sha'],
            cohort=payload['cohort'],supplier_calls=0,database_writes=0,mapping_writes=0,safe_to_write_now=False,no_replay=True)
        raw_result=json.dumps(data,sort_keys=True,separators=(',',':')).encode()
        return {'evidence_sha256':hashlib.sha256(raw_result).hexdigest(),'data':data}
    env={k:os.environ[k] for k in ('HOME','PATH','LANG','LC_ALL') if k in os.environ}
    env['MATCH_RETAINED_DIR']=str(op)
    env['MATCH_RETAINED_SOURCE_ROOT']=str(stage)
    if mode=='match-tourvisor-quota-policy':
        env['MATCH_QUOTA_ACTION']='apply-owner-5000'
        args=['python3',str(stage/'scripts/diagnostics/hotel_match_tourvisor_quota_v1.py')]
    else:
        disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
        args=['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',
              '-d','memory_limit=512M','-d','disable_functions='+disabled,
              str(stage/'scripts/diagnostics/hotel_match_retained_intake_v1.php')]
    run=subprocess.run(args,cwd=project,env=env,capture_output=True,text=True,timeout=600)
    if run.returncode or run.stderr.strip() or len(run.stdout.encode())>65536:
        failure={'exit_code':run.returncode,'stdout_sha256':hashlib.sha256(run.stdout.encode()).hexdigest(),
            'stderr_sha256':hashlib.sha256(run.stderr.encode()).hexdigest()}
        try:
            child=json.loads(run.stdout)
            failure['child_error']={k:child.get(k) for k in ('state','error_code','error_class','error_sha256')}
        except Exception:pass
        result['match_retained_child_failure']=failure
        fail('retained_child_failed_no_replay')
    data=json.loads(run.stdout)
    if mode=='match-retained-intake':
        raw=op/'retained-raw.ndjson'
        if not safe_file(raw,134217728) or raw.stat().st_nlink!=1:fail('retained_raw_missing')
        run2=subprocess.run(['python3',str(stage/'scripts/diagnostics/hotel_match_retained_projection_v1.py'),str(raw)],
            cwd=project,env=env,capture_output=True,text=True,timeout=240)
        if run2.returncode or run2.stderr.strip() or len(run2.stdout.encode())>32*1024*1024:
            fail('retained_projection_failed_no_replay')
        projected=json.loads(run2.stdout)
        if (data.get('state')!='captured_read_only' or projected.get('state')!='projected_read_only'
                or data.get('raw_sha256')!=projected.get('raw_sha256')
                or data.get('raw_bytes')!=projected.get('raw_bytes')
                or any(data.get('rows',{}).get(k)!=projected.get('capture_rows',{}).get(k,0)
                       for k in ('observation','legacy_source','identity','target'))
                or projected.get('safe_to_write_now') is not False
                or any(type(projected.get(k)) is not int or projected[k]!=0
                       for k in ('supplier_calls','database_writes','mapping_writes','new_accepted_mappings'))):
            fail('retained_capture_projection_binding')
        data={'capture':data,'projection':projected,'state':'captured_and_projected_read_only'}
    else:
        if (data.get('account')!='TOURVISOR_ANEX_JWT' or data.get('policy')!='physical-http-v1'
                or data.get('daily_physical_http_limit')!=5000 or data.get('supplier_calls')!=0
                or data.get('operations_preserved') is not True or data.get('global_usage')!='not_measured'):
            fail('retained_quota_policy_binding')
        data={'quota_policy':data,'state':'quota_policy_applied_no_supplier_calls'}
    if hashlib.sha256(config.read_bytes()).hexdigest()!=config_hash:fail('retained_config_drift')
    data.update(operation_id=operation,source_sha=source,control_source_sha=payload['match_retained_control_sha'],
        cohort=payload['cohort'],supplier_calls=0,database_writes=0,mapping_writes=0,
        safe_to_write_now=False,no_replay=True)
    raw_result=json.dumps(data,sort_keys=True,separators=(',',':')).encode()
    fd=os.open(op/'retained-evidence.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:
        handle.write(raw_result);handle.flush();os.fsync(handle.fileno())
    return {'evidence_sha256':hashlib.sha256(raw_result).hexdigest(),'data':data}
'''

REMOTE_DISPATCH = r'''    if mode in RETAINED_MODES:
        result['supplier_calls']=result['database_writes']=result['mapping_writes']=0
        if mode=='match-retained-acquire':result['supplier_calls']=None
        if mode in ('match-retained-anex-write','match-retained-anex-write-v2'):result['database_writes']=result['mapping_writes']=None
        result['match_retained']=run_match_retained(stage)
        if mode in ('match-retained-anex-write','match-retained-anex-write-v2'):
            result['database_writes']=result['match_retained']['data']['database_writes']
            result['mapping_writes']=result['match_retained']['data']['mapping_writes']
        if mode=='match-retained-acquire':result['supplier_calls']=result['match_retained']['data']['supplier_calls']
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True;result['status']='complete'
'''


OPERATOR_BINDING_HANDLER = r'''
def operator_dictionary_projection(value):
    aliases = {
        'anex': {'anex', 'anex tour', 'анекс тур'},
        'biblio': {'библио-глобус', 'библио глобус', 'biblio-globus', 'biblio globus'},
        'funsun': {'fun&sun', 'fun & sun', 'фан&сан', 'фан & сан'},
        'intourist': {'интурист', 'intourist'},
    }
    if not isinstance(value, list) or len(value) > 1000:
        raise ValueError('operator_dictionary_shape')
    seen = set(); bindings = {}
    for row in value:
        if (not isinstance(row, dict) or not set(row).issubset({'id', 'name', 'fullName', 'russianName'})
                or type(row.get('id')) is not int or not 1 <= row['id'] <= 2147483647
                or row['id'] in seen):
            raise ValueError('operator_dictionary_row')
        seen.add(row['id']); labels = []
        for key in ('name', 'fullName', 'russianName'):
            if key not in row: continue
            label = row[key]
            if (not isinstance(label, str) or len(label) > 255
                    or any(ord(c) < 32 or ord(c) == 127 for c in label)):
                raise ValueError('operator_dictionary_label')
            if label.strip(): labels.append(' '.join(label.casefold().split()))
        if not labels: raise ValueError('operator_dictionary_label')
        families = {family for family, names in aliases.items() if any(label in names for label in labels)}
        if len(families) > 1: raise ValueError('operator_dictionary_conflict')
        if families:
            family = next(iter(families))
            if family in bindings: raise ValueError('operator_dictionary_ambiguous')
            bindings[family] = row['id']
    return [{'family': family, 'operator_id': ident} for family, ident in sorted(bindings.items())]

def retained_operator_dictionary(stage):
    import importlib.util
    import sys
    profile = NEXT_SCOPE
    if (mode != 'match-retained-acquire' or payload.get('cohort') != profile['cohort']
            or operation != profile['operation_id'] or payload.get('target_operation_id') != profile['parent_operation_id']):
        fail('operator_dictionary_scope')
    protected = {}
    def retain(path, bound):
        if not safe_file(path, bound) or path.resolve() != path or path.stat().st_nlink != 1:
            fail('operator_dictionary_protected_path')
        fd = os.open(path, os.O_RDONLY | os.O_NOFOLLOW)
        try:
            before = os.fstat(fd)
            with os.fdopen(os.dup(fd), 'rb') as handle: raw = handle.read(bound+1)
            after = os.fstat(fd)
        finally: os.close(fd)
        signature = lambda stat: (stat.st_dev, stat.st_ino, stat.st_size, stat.st_mtime_ns, stat.st_ctime_ns)
        if signature(before) != signature(after) or len(raw) != after.st_size or len(raw) > bound:
            fail('operator_dictionary_protected_drift')
        protected[path] = (hashlib.sha256(raw).hexdigest(), signature(after))
        return raw
    parent_dir = private/profile['parent_operation_id']
    if parent_dir.is_symlink() or parent_dir.resolve() != parent_dir: fail('operator_dictionary_parent_path')
    parent = json.loads(retain(parent_dir/'result.json', 32*1024*1024))
    reservation = json.loads(retain(parent_dir/'reservation.json', 65536))
    data = parent.get('match_retained', {}).get('data', {})
    digest = hashlib.sha256(json.dumps(data, sort_keys=True, separators=(',', ':')).encode()).hexdigest()
    if (parent.get('status') != 'complete' or parent.get('mode') != 'match-retained-current'
            or parent.get('production_unchanged') is not True or data.get('state') != 'current_retained_read_only'
            or digest != profile['parent_evidence_sha256'] or parent['match_retained'].get('evidence_sha256') != digest
            or any(obj.get('operation_id') != profile['parent_operation_id'] for obj in (parent, reservation, data))
            or any(obj.get('source_sha') != reservation.get('source_sha') for obj in (parent, data))
            or any(type(obj.get(key)) is not int or obj[key] != 0 for obj in (parent, data)
                   for key in ('supplier_calls', 'database_writes', 'mapping_writes'))):
        fail('operator_dictionary_parent_binding')
    retain(project/'config.php', 1048576)
    home = pathlib.Path.home()
    for name in ('retained-selection-claims.json', 'retained-context-claims.json'):
        retain(home/'.anytoour-match'/name, 8*1024*1024)
    sys.path.insert(0, str(stage/'scripts/diagnostics'))
    spec = importlib.util.spec_from_file_location('binding_acquisition', stage/'scripts/diagnostics/hotel_match_retained_acquire_v1.py')
    helper = importlib.util.module_from_spec(spec); spec.loader.exec_module(helper)
    client = helper.Client(home, project, op, operation)
    client.http_maximum = 1; client.search_start_maximum = 0; client.deadline = time.monotonic()+60
    outcome = dict(state='operator_dictionary_failed_no_replay', bindings=[], stop_reason=None)
    try:
        status, value, raw = client.call('dictionary', '/operators', {'departureId': 1, 'countryId': 1})
        outcome.update(raw_response=raw, http_status=status)
        body = retain(op/'response-0001.bin', helper.BODY_LIMIT)
        if hashlib.sha256(body).hexdigest() != raw['raw_sha256']:
            raise ValueError('operator_dictionary_raw_digest')
        def unique(pairs):
            result = {}
            for key, value in pairs:
                if key in result: raise ValueError('operator_dictionary_duplicate_key')
                result[key] = value
            return result
        value = json.loads(body, object_pairs_hook=unique)
        outcome['bindings'] = operator_dictionary_projection(value)
        outcome['state'] = 'operator_dictionary_captured'
    except Exception as error:
        # Exception text can contain sensitive supplier data; expose only its class.
        outcome['stop_reason'] = type(error).__name__
    for path, (expected, signature) in protected.items():
        if (not safe_file(path, 32*1024*1024) or path.resolve() != path or path.stat().st_nlink != 1
                or hashlib.sha256(path.read_bytes()).hexdigest() != expected
                or (path.stat().st_dev, path.stat().st_ino, path.stat().st_size,
                    path.stat().st_mtime_ns, path.stat().st_ctime_ns) != signature):
            fail('operator_dictionary_protected_drift')
    outcome.update(operation_id=operation, source_sha=source, control_source_sha=payload['match_retained_control_sha'],
        cohort=payload['cohort'], target_operation_id=profile['parent_operation_id'], parent_evidence_sha256=digest,
        account='TOURVISOR_ANEX_JWT', dictionary_scope={'departure_id': 1, 'country_id': 1},
        supplier_calls=client.calls, physical_http_reservations=client.reserved, unknown_http_calls=client.unknown,
        tariff_search_units=client.tariff, actions=dict(client.counts), database_writes=0, mapping_writes=0,
        new_database_reads=0, new_accepted_mappings=0, selected_hotels=0, native_hotel_authority=False,
        hotel_sales_access_proven=False, safe_to_write_now=False, no_replay=True,
        protected_inputs_unchanged=True, global_usage='not_measured')
    evidence = json.dumps(outcome, sort_keys=True, separators=(',', ':')).encode()
    helper.save_bytes(op/'retained-evidence.json', evidence)
    return {'data': outcome, 'evidence_sha256': hashlib.sha256(evidence).hexdigest()}
'''


OPERATOR_ALIAS_AUDIT_HANDLER = r'''
def retained_operator_alias_audit(stage):
    import re
    profile = NEXT_SCOPE
    def canonical(value): return json.dumps(value, sort_keys=True, separators=(',', ':')).encode()
    def digest(value): return hashlib.sha256(canonical(value)).hexdigest()
    def unique(pairs):
        out = {}
        for key, value in pairs:
            if key in out: raise ValueError('alias_duplicate_json_key')
            out[key] = value
        return out
    protected = {}
    def read(path, limit=16*1024*1024):
        if not safe_file(path, limit) or path.resolve() != path or path.stat().st_nlink != 1: fail('alias_input_path')
        fd = os.open(path, os.O_RDONLY | os.O_NOFOLLOW)
        try:
            before = os.fstat(fd)
            with os.fdopen(os.dup(fd), 'rb') as handle: body = handle.read(limit+1)
            after = os.fstat(fd)
        finally: os.close(fd)
        signature = lambda s: (s.st_dev, s.st_ino, s.st_size, s.st_mtime_ns, s.st_ctime_ns)
        if signature(before) != signature(after) or len(body) > limit or len(body) != after.st_size: fail('alias_input_drift')
        protected[path] = (hashlib.sha256(body).hexdigest(), signature(after))
        return body
    if (mode != 'match-retained-readback' or operation != profile['operation_id']
            or payload.get('cohort') != profile['cohort'] or payload.get('target_operation_id') != profile['parent_operation_id']): fail('alias_fixed_scope')
    directory = private/profile['parent_operation_id']
    if directory.is_symlink() or directory.resolve() != directory: fail('alias_parent_path')
    parent = json.loads(read(directory/'result.json'), object_pairs_hook=unique)
    reservation = json.loads(read(directory/'reservation.json', 65536), object_pairs_hook=unique)
    data = parent.get('match_retained', {}).get('data', {})
    if (parent.get('status') != 'complete' or parent.get('mode') != 'match-retained-acquire'
            or parent.get('production_unchanged') is not True or data.get('state') != 'operator_dictionary_captured'
            or digest(data) != profile['parent_evidence_sha256'] or parent['match_retained'].get('evidence_sha256') != digest(data)
            or any(x.get('operation_id') != profile['parent_operation_id'] or x.get('source_sha') != profile['captured_source_sha'] for x in (parent,reservation,data))
            or data.get('dictionary_scope') != {'kind':'all_active_operators'}
            or data.get('raw_response') != {'response_number':1,'raw_sha256':profile['raw_sha256']}): fail('alias_parent_binding')
    manifest = json.loads(read(stage/'manifest.json', 65536), object_pairs_hook=unique)
    installed = json.loads(read(directory/'installed-source.json', 65536), object_pairs_hook=unique)
    if (not isinstance(manifest.get('files'),dict) or len(manifest['files']) != 17
            or installed != {'source_sha':profile['captured_source_sha'],'files':manifest['files']}): fail('alias_installed_binding')
    raw = read(directory/'response-0001.bin')
    if hashlib.sha256(raw).hexdigest() != profile['raw_sha256']: fail('alias_raw_digest')
    rows = json.loads(raw, object_pairs_hook=unique)
    if operator_dictionary_projection(rows) != data.get('bindings'): fail('alias_existing_projection')
    candidates = []
    for row in rows:
        labels = [(key,row[key]) for key in ('name','fullName','russianName') if key in row]
        normalized = ' '.join(label.casefold() for _,label in labels)
        if not ('tui' in normalized or 'туи' in normalized or ('fun' in normalized and 'sun' in normalized)
                or ('фан' in normalized and 'сан' in normalized)): continue
        candidates.append({'operator_id':row['id'],'candidate_only':True,'labels':[
            {'field':key,'value':label if len(label)<=100 and re.fullmatch(OPERATOR_ALIAS_LABEL_PATTERN,label,re.I) else None,
             'sha256':hashlib.sha256(label.encode()).hexdigest()} for key,label in labels]})
    if len(candidates) > 8: fail('alias_candidate_bound')
    home = pathlib.Path.home()/'.anytoour-match'
    for relative in ('retained-selection-claims.json','retained-context-claims.json','provider-quotas/tourvisor-anex-2026-10-22.json'):
        read(home/relative, 8*1024*1024)
    for path,(expected,signature) in protected.items():
        st = path.stat()
        if (not safe_file(path,16*1024*1024) or path.resolve() != path or st.st_nlink != 1
                or hashlib.sha256(path.read_bytes()).hexdigest() != expected
                or (st.st_dev,st.st_ino,st.st_size,st.st_mtime_ns,st.st_ctime_ns) != signature): fail('alias_input_drift')
    outcome = dict(state='retained_operator_alias_audit_completed',operation_id=operation,source_sha=source,
        control_source_sha=payload['match_retained_control_sha'],cohort=payload['cohort'],target_operation_id=profile['parent_operation_id'],
        parent_evidence_sha256=profile['parent_evidence_sha256'],captured_source_sha=profile['captured_source_sha'],
        raw_sha256=profile['raw_sha256'],alias_candidates=candidates,candidate_count=len(candidates),
        prior_bindings_preserved=True,installed_metadata_verified=True,protected_inputs_count=len(protected),protected_inputs_unchanged=True,
        supplier_calls=0,physical_http_reservations=0,tariff_search_units=0,unknown_http_calls=0,
        new_database_reads=0,database_writes=0,mapping_writes=0,booking_calls=0,lead_calls=0,new_accepted_mappings=0,
        account_sales_authority=False,operator_family_binding_authority=False,native_hotel_authority=False,safe_to_write_now=False,
        global_usage='not_measured',no_replay=True)
    evidence = canonical(outcome)
    fd = os.open(op/'retained-evidence.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle: handle.write(evidence); handle.flush(); os.fsync(handle.fileno())
    return {'data':outcome,'evidence_sha256':hashlib.sha256(evidence).hexdigest()}
'''


def operator_alias_audit_handler():
    anchor = '\ndef retained_operator_dictionary(stage):'
    need(OPERATOR_BINDING_HANDLER.count(anchor) == 1, 'alias_projection_anchor')
    return 'OPERATOR_ALIAS_LABEL_PATTERN='+repr(OPERATOR_ALIAS_LABEL_PATTERN)+'\n'+OPERATOR_BINDING_HANDLER.split(anchor, 1)[0] + OPERATOR_ALIAS_AUDIT_HANDLER


BG_IDENTITY_HANDLER = r'''
def retained_bg_identity(stage):
    import importlib.util
    import sys
    import fcntl
    import datetime
    from zoneinfo import ZoneInfo
    profile = NEXT_SCOPE
    def canonical(value): return json.dumps(value, sort_keys=True, separators=(',', ':')).encode()
    def digest(value): return hashlib.sha256(canonical(value)).hexdigest()
    def unique(pairs):
        out = {}
        for key, value in pairs:
            if key in out: raise ValueError('bg_duplicate_json_key')
            out[key] = value
        return out
    protected = {}
    def retained(path, limit=32*1024*1024):
        if not safe_file(path, limit) or path.resolve() != path or path.stat().st_nlink != 1:
            fail('bg_protected_path')
        before = path.stat(); body = path.read_bytes(); after = path.stat()
        signature = lambda s: (s.st_dev, s.st_ino, s.st_size, s.st_mtime_ns, s.st_ctime_ns)
        if signature(before) != signature(after) or len(body) != after.st_size: fail('bg_protected_drift')
        protected[path] = (hashlib.sha256(body).hexdigest(), signature(after))
        return body
    def receipt(name, mode_name, evidence, receipt_hash=None):
        directory = private/name
        if directory.is_symlink() or directory.resolve() != directory: fail('bg_parent_path')
        parent = json.loads(retained(directory/'result.json'), object_pairs_hook=unique)
        reservation = json.loads(retained(directory/'reservation.json', 65536), object_pairs_hook=unique)
        data = parent.get('match_retained', {}).get('data', {})
        if (parent.get('status') != 'complete' or parent.get('mode') != mode_name
                or parent.get('production_unchanged') is not True or digest(data) != evidence
                or parent.get('match_retained', {}).get('evidence_sha256') != evidence
                or (receipt_hash is not None and digest(parent) != receipt_hash)
                or any(x.get('operation_id') != name for x in (parent, reservation, data))
                or any(x.get('source_sha') != reservation.get('source_sha') for x in (parent, data))):
            fail('bg_parent_binding')
        return data
    if (mode != 'match-retained-acquire' or operation != profile['operation_id']
            or payload.get('cohort') != profile['cohort'] or payload.get('target_operation_id') != profile['parent_operation_id']):
        fail('bg_fixed_scope')
    binding = receipt(profile['parent_operation_id'], 'match-retained-acquire', profile['parent_evidence_sha256'])
    if (binding.get('state') != 'operator_dictionary_captured' or binding.get('account') != 'TOURVISOR_ANEX_JWT'
            or {'family': 'biblio', 'operator_id': profile['operator_id']} not in binding.get('bindings', [])
            or type(binding.get('unknown_http_calls')) is not int or binding['unknown_http_calls'] != 0): fail('bg_operator_binding')
    current = receipt(profile['current_operation_id'], 'match-retained-current', profile['current_evidence_sha256'], profile['current_receipt_sha256'])
    rows = [r for r in current.get('rows', []) if r.get('tv_hotel_id') == profile['tv_hotel_id']]
    if len(rows) != 1 or digest(rows[0]) != profile['target_row_sha256']: fail('bg_target_row')
    row = rows[0]; context = row.get('future_context', {}); wire = profile['wire']
    if (row.get('anchor_state') != 'canonical_anchor_ok' or row.get('target', {}).get('country_id') != 1
            or row.get('target', {}).get('is_active') != 1
            or any(row.get('accepted_provider_ids', {}).get(ns) for ns in ('bgoperator', 'operator_115'))): fail('bg_target_already_bound_or_invalid')
    if (context != {'adults': 1, 'child_ages_signature': '13', 'children_count': 1, 'country_id': 1,
                    'departure_date': '2026-10-24', 'departure_id': 11, 'hotel_id': 321, 'nights': 6,
                    'observed_at': '2026-10-08 00:57:29', 'source': 'user_search'}
            or datetime.datetime.now(ZoneInfo('Europe/Moscow')).date().isoformat() > wire['dateFrom']): fail('bg_saved_party_or_date')
    home = pathlib.Path.home()
    retained(project/'config.php', 1048576)
    retained(home/'.anytoour-match/retained-selection-claims.json', 8*1024*1024)
    ledger = home/'.anytoour-match/retained-context-claims.json'
    if ledger.is_symlink() or ledger.resolve() != ledger or not safe_file(ledger, 8*1024*1024): fail('bg_context_path')
    identity = {'account': 'TOURVISOR_ANEX_JWT', 'namespace': 'tourvisor_operator:18',
                'operator_id': 18, 'tv_hotel_id': 321, 'wire': wire}
    with os.fdopen(os.open(ledger, os.O_RDWR | os.O_NOFOLLOW), 'r+b') as handle:
        fcntl.flock(handle, fcntl.LOCK_EX)
        opened = os.fstat(handle.fileno()); named = ledger.lstat()
        if (opened.st_nlink != 1 or (opened.st_dev, opened.st_ino) != (named.st_dev, named.st_ino)
                or ledger.is_symlink() or ledger.resolve() != ledger): fail('bg_context_path')
        old = handle.read(8*1024*1024+1)
        if len(old) > 8*1024*1024: fail('bg_context_bound')
        claims = json.loads(old, object_pairs_hook=unique)
        if type(claims.get('schema_version')) is not int or claims['schema_version'] != 1 or not isinstance(claims.get('claims'), dict): fail('bg_context_shape')
        key = digest(identity)
        if key in claims['claims']: fail('bg_context_consumed_no_replay')
        for prior in claims['claims'].values():
            c = prior.get('context', {})
            if c.get('account') == identity['account'] and c.get('operator_id') == 18 and c.get('tv_hotel_id') == 321:
                fail('bg_prior_target_claim')
        claims['claims'][key] = {'context': identity, 'operation_id': operation, 'state': 'reserved_before_http_no_replay'}
        encoded = canonical(claims); handle.seek(0); handle.write(encoded); handle.truncate(); handle.flush(); os.fsync(handle.fileno())
        handle.seek(0)
        if handle.read() != encoded: fail('bg_context_write_unknown_no_replay')
        written = os.fstat(handle.fileno())
        context_signature = (written.st_dev, written.st_ino, written.st_size, written.st_mtime_ns, written.st_ctime_ns)
        directory_fd = os.open(ledger.parent, os.O_RDONLY | os.O_DIRECTORY)
        try: os.fsync(directory_fd)
        finally: os.close(directory_fd)
    context_hash = hashlib.sha256(encoded).hexdigest()
    sys.path.insert(0, str(stage/'scripts/diagnostics'))
    spec = importlib.util.spec_from_file_location('bg_existing_acquisition', stage/'scripts/diagnostics/hotel_match_retained_acquire_v1.py')
    helper = importlib.util.module_from_spec(spec); spec.loader.exec_module(helper)
    shape = helper.shape
    import hotel_match_retained_projection_v1 as projection
    client = helper.Client(home, project, op, operation)
    client.http_maximum = 6; client.search_start_maximum = 1; client.deadline = time.monotonic()+60
    outcome = dict(state='bg_offer_identity_partial_no_replay', search_complete=False, operator_offer_observed=False,
                   detail_identity_verified=False, native_candidate_count=0, native_candidate_namespace=None,
                   native_candidate_id_sha256=None, operator_link_state='not_observed', stop_reason=None)
    raws = []
    def call(action, path, params):
        status, value, raw = client.call(action, path, params); raws.append(raw)
        body = retained(op/('response-%04d.bin' % raw['response_number']), helper.BODY_LIMIT)
        if hashlib.sha256(body).hexdigest() != raw['raw_sha256']: fail('bg_raw_digest')
        return status, json.loads(body, object_pairs_hook=unique)
    try:
        _, start = call('search_start', '/tours/search', wire); start = shape.unwrap(start)
        sid = shape.num(start.get('searchId') or start.get('id')) if isinstance(start, dict) else None
        if sid is None: raise ValueError('bg_search_id_missing')
        for delay in (2, 4, 7):
            time.sleep(delay)
            _, status = call('search_status', '/tours/search/%s/status' % sid, {'operatorStatus': False})
            if shape.ready(status): outcome['search_complete'] = True; break
        if not outcome['search_complete']: raise ValueError('bg_search_not_complete')
        _, found = call('search_results', '/tours/search/%s' % sid, {'limit': 10000})
        hotels = [h for h in shape.hotel_rows(found) if isinstance(h, dict) and shape.num(h.get('id')) == 321]
        if len(hotels) > 1: raise ValueError('bg_duplicate_target')
        tours = [t for h in hotels for t in h.get('tours', []) if isinstance(t, dict) and shape.ident(t, 'operator') == 18
                 and shape.num(t.get('id') or t.get('tourId'))]
        if tours:
            outcome['operator_offer_observed'] = True
            tid = shape.num(tours[0].get('id') or tours[0].get('tourId'))
            status, detail = call('tour_detail', '/tours/%s' % tid, {'currency': 'RUB'})
            detail = shape.unwrap(detail)
            if status != 200 or not isinstance(detail, dict) or shape.ident(detail, 'hotel') != 321 or shape.ident(detail, 'operator') != 18:
                raise ValueError('bg_detail_identity_mismatch')
            outcome['detail_identity_verified'] = True
            url = detail.get('operatorLink'); candidates = projection.native_link(url)
            candidates = [c for c in candidates if c['namespace'] == 'operator_url:bgoperator.ru:f4']
            outcome['operator_link_state'] = 'url_native_candidate' if candidates else 'no_proven_native_identifier'
            if len(candidates) == 1:
                candidate = candidates[0]
                helper.save_json(op/'bg-native-candidate.json', dict(candidate, tv_hotel_id=321, operator_id=18,
                    source_raw=raws[-1], native_hotel_authority=False, independent_namespace_bridge_required=True))
                outcome.update(native_candidate_count=1, native_candidate_namespace=candidate['namespace'],
                    native_candidate_id_sha256=hashlib.sha256(candidate['external_id'].encode()).hexdigest())
        outcome['state'] = 'bg_offer_identity_completed_read_only'
    except Exception as error:
        # Supplier exception text/URLs/values never enter the public receipt.
        allowed = {'bg_search_id_missing', 'bg_search_not_complete', 'bg_duplicate_target', 'bg_detail_identity_mismatch'}
        outcome['stop_reason'] = str(error) if str(error) in allowed else type(error).__name__
    for path, (expected, signature) in protected.items():
        st = path.stat()
        if (not safe_file(path, 32*1024*1024) or path.resolve() != path or st.st_nlink != 1
                or hashlib.sha256(path.read_bytes()).hexdigest() != expected
                or (st.st_dev, st.st_ino, st.st_size, st.st_mtime_ns, st.st_ctime_ns) != signature): fail('bg_protected_drift')
    stat = ledger.stat()
    if (not safe_file(ledger, 8*1024*1024) or ledger.is_symlink() or ledger.resolve() != ledger or stat.st_nlink != 1
            or (stat.st_dev, stat.st_ino, stat.st_size, stat.st_mtime_ns, stat.st_ctime_ns) != context_signature
            or hashlib.sha256(ledger.read_bytes()).hexdigest() != context_hash): fail('bg_context_drift')
    outcome.update(operation_id=operation, source_sha=source, control_source_sha=payload['match_retained_control_sha'],
        cohort=payload['cohort'], target_operation_id=profile['parent_operation_id'],
        parent_evidence_sha256=profile['parent_evidence_sha256'], current_evidence_sha256=profile['current_evidence_sha256'],
        target_row_sha256=profile['target_row_sha256'], account='TOURVISOR_ANEX_JWT', operator_id=18, tv_hotel_id=321,
        context_reservation_sha256=context_hash, context_claim_namespace='tourvisor_operator:18', raw_responses=raws,
        supplier_calls=client.calls, physical_http_reservations=client.reserved, unknown_http_calls=client.unknown,
        tariff_search_units=client.tariff, actions=dict(client.counts), database_writes=0, mapping_writes=0, booking_calls=0, lead_calls=0,
        new_database_reads=0, new_accepted_mappings=0, native_hotel_authority=False, hotel_sales_access_proven=False,
        safe_to_write_now=False, no_replay=True, protected_inputs_unchanged=True, global_usage='not_measured')
    helper.save_bytes(op/'retained-evidence.json', canonical(outcome))
    return {'data': outcome, 'evidence_sha256': digest(outcome)}
'''


def funsun_ru_identity_handler():
    """Derive only the new fixed profile; sealed BG generated bytes stay intact."""
    handler = BG_IDENTITY_HANDLER.replace('bg_', 'funsun_ru_')
    handler = re.sub(r'\b18\b', '25', handler)
    old = """    binding = receipt(profile['parent_operation_id'], 'match-retained-acquire', profile['parent_evidence_sha256'])
    if (binding.get('state') != 'operator_dictionary_captured' or binding.get('account') != 'TOURVISOR_ANEX_JWT'
            or {'family': 'biblio', 'operator_id': profile['operator_id']} not in binding.get('bindings', [])
            or type(binding.get('unknown_http_calls')) is not int or binding['unknown_http_calls'] != 0): fail('funsun_ru_operator_binding')
"""
    new = """    binding = receipt(profile['parent_operation_id'], 'match-retained-readback',
                      profile['parent_evidence_sha256'], profile['parent_receipt_sha256'])
    rows = binding.get('alias_candidates', [])
    selected = [r for r in rows if type(r.get('operator_id')) is int and r['operator_id'] == 25]
    expected_labels = [{'field':key,'value':None,'sha256':profile['alias_literal_sha256']}
                       for key in ('name','fullName','russianName')]
    if (binding.get('source_sha') != profile['alias_source_sha']
            or binding.get('state') != 'retained_operator_alias_audit_completed'
            or binding.get('parent_evidence_sha256') != 'ca613f2afe8ba344fdf57f1232ad174a42aea430ab44bbffa4a037b67a9f0291'
            or binding.get('raw_sha256') != '3f915ea52e17f12eb036a2dd4835418d78c0b387551b689884b593b3d18bd905'
            or hashlib.sha256(profile['alias_literal'].encode()).hexdigest() != profile['alias_literal_sha256']
            or selected != [{'operator_id':25,'candidate_only':True,'labels':expected_labels}]
            or binding.get('protected_inputs_unchanged') is not True
            or type(binding.get('unknown_http_calls')) is not int or binding['unknown_http_calls'] != 0): fail('funsun_ru_operator_binding')
"""
    replacements = {
        old: new,
        "op/'bg-native-candidate.json'": "op/'funsun-ru-native-candidate.json'",
        "('bgoperator', 'operator_115')": "('operator_315',)",
        "candidates = [c for c in candidates if c['namespace'] == 'operator_url:bgoperator.ru:f4']":
            "candidates = [c for c in candidates if c['namespace'] in ('operator_url:fun-sun.ru:hotel-path', 'operator_url:fun-sun.ru:hotelid', 'operator_url:fun-sun.ru:hotel_id')][:1]",
        "target_row_sha256=profile['target_row_sha256'], account=":
            "target_row_sha256=profile['target_row_sha256'], alias_literal_sha256=profile['alias_literal_sha256'], account=",
    }
    for before, after in replacements.items():
        need(handler.count(before) == 1, 'funsun_ru_identity_anchor')
        handler = handler.replace(before, after, 1)
    return handler


def full_operator_binding_handler():
    # Preserve the consumed filtered operation's generated bytes. OpenAPI 1.2.4
    # defines the unfiltered endpoint as the full active operator dictionary.
    handler = OPERATOR_BINDING_HANDLER
    replacements = {
        "parent.get('mode') != 'match-retained-current'": "parent.get('mode') != 'match-retained-readback'",
        "data.get('state') != 'current_retained_read_only'": "data.get('state') != 'binding_failure_reconciled_before_supplier_call'",
        "client.call('dictionary', '/operators', {'departureId': 1, 'countryId': 1})": "client.call('dictionary', '/operators', {})",
        "dictionary_scope={'departure_id': 1, 'country_id': 1}": "dictionary_scope={'kind': 'all_active_operators'}",
    }
    for old, new in replacements.items():
        need(handler.count(old) == 1, 'full_operator_binding_anchor')
        handler = handler.replace(old, new, 1)
    return handler


BINDING_RECONCILIATION_HANDLER = r'''
def retained_binding_reconciliation(stage):
    profile = NEXT_SCOPE
    if (mode != 'match-retained-readback' or operation != profile['operation_id']
            or payload.get('cohort') != profile['cohort']
            or payload.get('target_operation_id') != profile['parent_operation_id']):
        fail('binding_reconciliation_scope')
    protected = {}
    def read(path, bound=1048576):
        if not safe_file(path, bound) or path.resolve() != path or path.stat().st_nlink != 1:
            fail('binding_reconciliation_path')
        fd = os.open(path, os.O_RDONLY | os.O_NOFOLLOW)
        try:
            before = os.fstat(fd)
            with os.fdopen(os.dup(fd), 'rb') as handle: raw = handle.read(bound+1)
            after = os.fstat(fd)
        finally: os.close(fd)
        signature = lambda s: (s.st_dev, s.st_ino, s.st_size, s.st_mtime_ns, s.st_ctime_ns)
        if signature(before) != signature(after) or len(raw) != after.st_size or len(raw) > bound:
            fail('binding_reconciliation_drift')
        protected[path] = (hashlib.sha256(raw).hexdigest(), signature(after))
        return raw
    def unique(pairs):
        out = {}
        for key, value in pairs:
            if key in out: fail('binding_reconciliation_duplicate_key')
            out[key] = value
        return out
    def parsed(path): return json.loads(read(path), object_pairs_hook=unique)
    target = private/profile['parent_operation_id']
    if target.is_symlink() or target.resolve() != target or not target.is_dir():
        fail('binding_reconciliation_target')
    expected_names = {'reservation.json', 'result.json', 'source'}
    if set(p.name for p in target.iterdir()) != expected_names: fail('binding_reconciliation_request_present')
    prior = parsed(target/'result.json'); reservation = parsed(target/'reservation.json')
    prior_hash = hashlib.sha256(json.dumps(prior, sort_keys=True, separators=(',', ':')).encode()).hexdigest()
    if (prior_hash != profile['failed_canonical_sha256'] or prior.get('status') != 'unknown_no_replay'
            or prior.get('supplier_calls') is not None
            or any(obj.get('operation_id') != profile['parent_operation_id']
                   or obj.get('source_sha') != profile['failed_source_sha']
                   or obj.get('mode') != 'match-retained-acquire' for obj in (prior, reservation))
            or any(type(prior.get(k)) is not int or prior[k] != 0
                   for k in ('database_writes', 'mapping_writes', 'lead_calls', 'booking_calls'))):
        fail('binding_reconciliation_failed_receipt')
    current_manifest = parsed(stage/'manifest.json')
    prior_manifest = parsed(target/'source/manifest.json')
    if (current_manifest != prior_manifest or type(current_manifest.get('schema_version')) is not int
            or current_manifest['schema_version'] != 1
            or not isinstance(current_manifest.get('files'), dict) or len(current_manifest['files']) != 17):
        fail('binding_reconciliation_manifest')
    for relative, expected in current_manifest['files'].items():
        pure = pathlib.PurePosixPath(relative)
        if pure.is_absolute() or '..' in pure.parts or not re.fullmatch(r'[a-f0-9]{64}', str(expected)):
            fail('binding_reconciliation_manifest')
        if hashlib.sha256(read(target/'source'/relative)).hexdigest() != expected:
            fail('binding_reconciliation_source')
    home = pathlib.Path.home()/'.anytoour-match'
    history = {}
    for name in ('retained-selection-claims.json', 'retained-context-claims.json',
                 'provider-quotas/tourvisor-anex-2026-10-20.json'):
        history[name] = hashlib.sha256(read(home/name, 8*1024*1024)).hexdigest()
    day = parsed(home/('provider-quotas/tourvisor-anex-'+profile['provider_day']+'.json'))
    if (day.get('provider') != 'tourvisor-anex' or day.get('provider_day') != profile['provider_day']
            or day.get('quota_policy') != 'physical-http-v1'
            or type(day.get('physical_http_daily_limit')) is not int or day['physical_http_daily_limit'] != 5000
            or any(type(day.get(k)) is not int or day[k] < 0 for k in ('physical_http_attempts', 'tariff_search_units'))
            or not isinstance(day.get('operations'), dict)
            or not isinstance(day.get('physical_http_reservations', {}), dict)
            or profile['parent_operation_id'] in day['operations']
            or any(k.startswith(profile['parent_operation_id']+':') for k in day.get('physical_http_reservations', {}))):
        fail('binding_reconciliation_day_reservation')
    for path, (digest, signature) in protected.items():
        if (not safe_file(path, 8*1024*1024) or path.resolve() != path or path.stat().st_nlink != 1
                or hashlib.sha256(path.read_bytes()).hexdigest() != digest
                or (path.stat().st_dev, path.stat().st_ino, path.stat().st_size,
                    path.stat().st_mtime_ns, path.stat().st_ctime_ns) != signature):
            fail('binding_reconciliation_drift')
    if set(p.name for p in target.iterdir()) != expected_names: fail('binding_reconciliation_request_present')
    data = dict(state='binding_failure_reconciled_before_supplier_call', operation_id=operation, source_sha=source,
        control_source_sha=payload['match_retained_control_sha'], cohort=payload['cohort'],
        target_operation_id=profile['parent_operation_id'], failed_source_sha=profile['failed_source_sha'],
        failed_canonical_sha256=prior_hash, prior_status='unknown_no_replay', prior_receipt_preserved=True,
        prior_supplier_calls=None, proved_failed_operation_supplier_calls=0, request_files_absent=True,
        failed_operation_day_reservations=0, staged_source_files_verified=17, provider_day=profile['provider_day'],
        historical_ledger_sha256=history, protected_inputs_count=len(protected), protected_inputs_unchanged=True,
        supplier_calls=0, database_writes=0, mapping_writes=0, booking_calls=0, lead_calls=0,
        new_database_reads=0, new_accepted_mappings=0,
        native_hotel_authority=False, hotel_sales_access_proven=False, safe_to_write_now=False,
        no_replay=True, global_usage='not_measured')
    return {'data': data, 'evidence_sha256': hashlib.sha256(json.dumps(data, sort_keys=True, separators=(',', ':')).encode()).hexdigest()}
'''


def binding_reconciliation_v2_handler():
    handler = BINDING_RECONCILIATION_HANDLER
    names = "    expected_names = {'reservation.json', 'result.json', 'source'}"
    anchor = "    home = pathlib.Path.home()/'.anytoour-match'"
    return_anchor = "    return {'data': data, 'evidence_sha256':"
    need(handler.count(names) == handler.count(anchor) == handler.count(return_anchor) == 1,
         'binding_reconciliation_v2_anchors')
    handler = handler.replace(names, "    expected_names = {'reservation.json', 'result.json', 'source', 'installed-source.json'}", 1)
    validation = r'''
    installed = parsed(target/'installed-source.json')
    if installed != {'source_sha': profile['failed_source_sha'], 'files': current_manifest['files']}:
        fail('binding_reconciliation_installed_metadata')
    previous = private/profile['prior_reconciliation_id']
    if previous.is_symlink() or previous.resolve() != previous or not previous.is_dir():
        fail('binding_reconciliation_previous_path')
    if set(p.name for p in previous.iterdir()) != expected_names:
        fail('binding_reconciliation_previous_members')
    previous_result = parsed(previous/'result.json')
    previous_reservation = parsed(previous/'reservation.json')
    previous_installed = parsed(previous/'installed-source.json')
    previous_digest = hashlib.sha256(json.dumps(previous_result, sort_keys=True, separators=(',', ':')).encode()).hexdigest()
    if (previous_digest != profile['prior_reconciliation_digest']
            or previous_result.get('status') != 'unknown_no_replay'
            or previous_result.get('reason') != 'binding_reconciliation_request_present'
            or any(obj.get('operation_id') != profile['prior_reconciliation_id']
                   or obj.get('source_sha') != profile['prior_reconciliation_source']
                   or obj.get('mode') != 'match-retained-readback' for obj in (previous_result, previous_reservation))
            or any(type(previous_result.get(k)) is not int or previous_result[k] != 0
                   for k in ('supplier_calls','database_writes','mapping_writes','lead_calls','booking_calls'))
            or previous_installed != {'source_sha': profile['prior_reconciliation_source'], 'files': current_manifest['files']}):
        fail('binding_reconciliation_previous_receipt')
'''
    handler = handler.replace(anchor, validation+'\n'+anchor, 1)
    handler = handler.replace(return_anchor,
        "    if set(p.name for p in previous.iterdir()) != expected_names: fail('binding_reconciliation_previous_members')\n"
        "    data.update(installed_metadata_verified=True, prior_reconciliation_preserved=True,\n"
        "        prior_reconciliation_operation_id=profile['prior_reconciliation_id'],\n"
        "        prior_reconciliation_canonical_sha256=previous_digest)\n"+return_anchor, 1)
    ast.parse(handler)
    return handler


def completed_binding_reconciliation(command, result, control_sha):
    try:
        data = result['match_retained']['data']
        v2 = command.get('cohort') == BINDING_RECONCILIATION_V2['cohort']
        profile = BINDING_RECONCILIATION_V2 if v2 else BINDING_RECONCILIATION
        if (command.get('mode') != 'match-retained-readback' or command.get('cohort') != profile['cohort']
                or command.get('operation_id') != profile['operation_id']
                or command.get('target_operation_id') != profile['parent_operation_id']
                or result.get('status') != 'complete' or result.get('production_unchanged') is not True
                or data.get('state') != 'binding_failure_reconciled_before_supplier_call'
                or data.get('control_source_sha') != control_sha
                or data.get('failed_canonical_sha256') != profile['failed_canonical_sha256']
                or data.get('failed_source_sha') != profile['failed_source_sha']
                or data.get('provider_day') != profile['provider_day']
                or data.get('prior_status') != 'unknown_no_replay' or data.get('prior_supplier_calls') is not None): return False
        for key in ('mode', 'source_sha', 'operation_id'):
            if result.get(key) != command.get(key): return False
        for key in ('source_sha', 'operation_id', 'cohort', 'target_operation_id'):
            if data.get(key) != command.get(key): return False
        for obj in (result, data):
            if any(type(obj.get(k)) is not int or obj[k] != 0 for k in ('supplier_calls','database_writes','mapping_writes','booking_calls','lead_calls')): return False
        for key in ('new_database_reads','new_accepted_mappings','proved_failed_operation_supplier_calls','failed_operation_day_reservations'):
            if type(data.get(key)) is not int or data[key] != 0: return False
        if type(data.get('staged_source_files_verified')) is not int or data['staged_source_files_verified'] != 17: return False
        if type(data.get('protected_inputs_count')) is not int or data['protected_inputs_count'] != (29 if v2 else 25): return False
        if v2 and (data.get('installed_metadata_verified') is not True or data.get('prior_reconciliation_preserved') is not True
                   or data.get('prior_reconciliation_operation_id') != profile['prior_reconciliation_id']
                   or data.get('prior_reconciliation_canonical_sha256') != profile['prior_reconciliation_digest']): return False
        if any(data.get(k) is not True for k in ('prior_receipt_preserved','request_files_absent','protected_inputs_unchanged','no_replay')): return False
        if any(data.get(k) is not False for k in ('native_hotel_authority','hotel_sales_access_proven','safe_to_write_now')): return False
        if data.get('global_usage') != 'not_measured': return False
        history = data.get('historical_ledger_sha256')
        if (not isinstance(history,dict) or set(history) != {'retained-selection-claims.json','retained-context-claims.json','provider-quotas/tourvisor-anex-2026-10-20.json'}
                or any(not re.fullmatch(r'[a-f0-9]{64}',str(v)) for v in history.values())): return False
        return result['match_retained'].get('evidence_sha256') == hashlib.sha256(canonical(data)).hexdigest()
    except (KeyError,TypeError,ValueError,AttributeError):
        return False


def completed_operator_dictionary(command, result, control_sha, profile=None):
    try:
        profile = OPERATOR_BINDING if profile is None else profile
        full = profile is OPERATOR_BINDING_FULL
        scope = {'kind': 'all_active_operators'} if full else {'departure_id': 1, 'country_id': 1}
        data = result['match_retained']['data']
        if (command.get('mode') != 'match-retained-acquire' or command.get('cohort') != profile['cohort']
                or command.get('operation_id') != profile['operation_id']
                or command.get('target_operation_id') != profile['parent_operation_id']
                or result.get('status') != 'complete' or result.get('production_unchanged') is not True
                or data.get('state') != 'operator_dictionary_captured' or data.get('stop_reason') is not None
                or data.get('control_source_sha') != control_sha
                or data.get('account') != 'TOURVISOR_ANEX_JWT'
                or data.get('parent_evidence_sha256') != profile['parent_evidence_sha256']
                or data.get('dictionary_scope') != scope
                or data.get('actions') != {'dictionary': 1}
                or type(data.get('http_status')) is not int or data['http_status'] != 200): return False
        if ((not full and any(type(data['dictionary_scope'].get(key)) is not int for key in ('departure_id', 'country_id')))
                or type(data['actions'].get('dictionary')) is not int): return False
        for key in ('mode', 'source_sha', 'operation_id'):
            if result.get(key) != command.get(key): return False
        for key in ('source_sha', 'operation_id', 'cohort', 'target_operation_id'):
            if data.get(key) != command.get(key): return False
        for obj in (result, data):
            for key in ('database_writes', 'mapping_writes'):
                if type(obj.get(key)) is not int or obj[key] != 0: return False
            if type(obj.get('supplier_calls')) is not int or obj['supplier_calls'] != 1: return False
        for key in ('physical_http_reservations',):
            if type(data.get(key)) is not int or data[key] != 1: return False
        for key in ('unknown_http_calls', 'tariff_search_units', 'new_database_reads', 'new_accepted_mappings', 'selected_hotels'):
            if type(data.get(key)) is not int or data[key] != 0: return False
        if any(data.get(key) is not False for key in ('native_hotel_authority', 'hotel_sales_access_proven', 'safe_to_write_now')): return False
        if any(data.get(key) is not True for key in ('no_replay', 'protected_inputs_unchanged')): return False
        raw = data.get('raw_response', {})
        if (not isinstance(raw, dict) or set(raw) != {'response_number', 'raw_sha256'}
                or type(raw.get('response_number')) is not int or raw['response_number'] != 1
                or not re.fullmatch(r'[a-f0-9]{64}', str(raw.get('raw_sha256', '')))): return False
        bindings = data.get('bindings')
        if not isinstance(bindings, list) or len(bindings) > 4: return False
        families = set(); ids = set()
        for row in bindings:
            if (not isinstance(row, dict) or set(row) != {'family', 'operator_id'}
                    or row['family'] not in ('anex', 'biblio', 'funsun', 'intourist') or row['family'] in families
                    or type(row['operator_id']) is not int or not 1 <= row['operator_id'] <= 2147483647
                    or row['operator_id'] in ids): return False
            families.add(row['family']); ids.add(row['operator_id'])
        return result['match_retained'].get('evidence_sha256') == hashlib.sha256(canonical(data)).hexdigest()
    except (KeyError, TypeError, AttributeError, ValueError):
        return False


def completed_bg_identity(command, result, control_sha, profile=None):
    try:
        p = BG_IDENTITY_SCOPE if profile is None else profile
        funsun = p is FUNSUN_RU_IDENTITY_SCOPE
        namespaces = ('operator_url:fun-sun.ru:hotel-path','operator_url:fun-sun.ru:hotelid','operator_url:fun-sun.ru:hotel_id') if funsun else ('operator_url:bgoperator.ru:f4',)
        d = result['match_retained']['data']
        if (command.get('mode') != 'match-retained-acquire' or command.get('cohort') != p['cohort']
                or command.get('operation_id') != p['operation_id'] or command.get('target_operation_id') != p['parent_operation_id']): return False
        for key in ('mode', 'source_sha', 'operation_id'):
            if result.get(key) != command.get(key): return False
        for key in ('source_sha', 'operation_id', 'cohort', 'target_operation_id'):
            if d.get(key) != command.get(key): return False
        if (result.get('status') != 'complete' or result.get('production_unchanged') is not True
                or d.get('control_source_sha') != control_sha or d.get('state') != ('funsun_ru_offer_identity_completed_read_only' if funsun else 'bg_offer_identity_completed_read_only')
                or d.get('search_complete') is not True or d.get('stop_reason') is not None
                or d.get('account') != 'TOURVISOR_ANEX_JWT' or type(d.get('operator_id')) is not int or d['operator_id'] != p['operator_id']
                or type(d.get('tv_hotel_id')) is not int or d['tv_hotel_id'] != 321
                or any(d.get(k) != p[k] for k in ('parent_evidence_sha256','current_evidence_sha256','target_row_sha256'))
                or d.get('context_claim_namespace') != 'tourvisor_operator:'+str(p['operator_id'])
                or not re.fullmatch('[a-f0-9]{64}', str(d.get('context_reservation_sha256', '')))): return False
        if funsun and d.get('alias_literal_sha256') != p['alias_literal_sha256']: return False
        actions = d.get('actions', {})
        if (not isinstance(actions, dict) or not set(actions).issubset({'search_start','search_status','search_results','tour_detail'})
                or any(type(v) is not int for v in actions.values()) or actions.get('search_start') != 1
                or actions.get('search_results') != 1 or not 1 <= actions.get('search_status', 0) <= 3
                or actions.get('tour_detail', 0) not in (0,1)): return False
        count = sum(actions.values())
        for obj in (result, d):
            for key in ('database_writes','mapping_writes','booking_calls','lead_calls'):
                if type(obj.get(key)) is not int or obj[key] != 0: return False
            if type(obj.get('supplier_calls')) is not int or obj['supplier_calls'] != count: return False
        for key, expected in [('physical_http_reservations',count),('tariff_search_units',1),('unknown_http_calls',0),
                              ('new_database_reads',0),('new_accepted_mappings',0)]:
            if type(d.get(key)) is not int or d[key] != expected: return False
        if not 3 <= count <= 6: return False
        for key in ('native_hotel_authority','hotel_sales_access_proven','safe_to_write_now'):
            if d.get(key) is not False: return False
        for key in ('no_replay','protected_inputs_unchanged'):
            if d.get(key) is not True: return False
        offer = d.get('operator_offer_observed'); detail = d.get('detail_identity_verified')
        if type(offer) is not bool or detail is not offer or actions.get('tour_detail',0) != int(offer): return False
        candidates = d.get('native_candidate_count')
        if type(candidates) is not int or candidates not in (0,1) or (candidates and not offer): return False
        if candidates:
            if (d.get('native_candidate_namespace') not in namespaces
                    or d.get('operator_link_state') != 'url_native_candidate'
                    or not re.fullmatch('[a-f0-9]{64}',str(d.get('native_candidate_id_sha256','')))): return False
        elif (d.get('native_candidate_namespace') is not None or d.get('native_candidate_id_sha256') is not None
              or d.get('operator_link_state') != ('no_proven_native_identifier' if offer else 'not_observed')): return False
        raws = d.get('raw_responses')
        if not isinstance(raws,list) or len(raws) != count: return False
        for i, raw in enumerate(raws,1):
            if (not isinstance(raw,dict) or set(raw) != {'response_number','raw_sha256'}
                    or type(raw['response_number']) is not int or raw['response_number'] != i
                    or not re.fullmatch('[a-f0-9]{64}',str(raw['raw_sha256']))): return False
        return d.get('global_usage') == 'not_measured' and result['match_retained'].get('evidence_sha256') == hashlib.sha256(canonical(d)).hexdigest()
    except (KeyError,TypeError,ValueError,AttributeError): return False


def completed_operator_alias_audit(command, result, control_sha):
    try:
        p = OPERATOR_ALIAS_AUDIT; d = result['match_retained']['data']
        if (command.get('mode') != 'match-retained-readback' or command.get('cohort') != p['cohort']
                or command.get('operation_id') != p['operation_id'] or command.get('target_operation_id') != p['parent_operation_id']
                or result.get('status') != 'complete' or result.get('production_unchanged') is not True
                or d.get('state') != 'retained_operator_alias_audit_completed' or d.get('control_source_sha') != control_sha): return False
        for key in ('mode','source_sha','operation_id'):
            if result.get(key) != command.get(key): return False
        for key in ('source_sha','operation_id','cohort','target_operation_id'):
            if d.get(key) != command.get(key): return False
        if any(d.get(k) != p[k] for k in ('parent_evidence_sha256','captured_source_sha','raw_sha256')): return False
        for obj in (result,d):
            for key in ('supplier_calls','database_writes','mapping_writes','booking_calls','lead_calls'):
                if type(obj.get(key)) is not int or obj[key] != 0: return False
        for key in ('physical_http_reservations','tariff_search_units','unknown_http_calls','new_database_reads','new_accepted_mappings'):
            if type(d.get(key)) is not int or d[key] != 0: return False
        if type(d.get('protected_inputs_count')) is not int or d['protected_inputs_count'] != 8: return False
        for key in ('prior_bindings_preserved','installed_metadata_verified','protected_inputs_unchanged','no_replay'):
            if d.get(key) is not True: return False
        for key in ('account_sales_authority','operator_family_binding_authority','native_hotel_authority','safe_to_write_now'):
            if d.get(key) is not False: return False
        rows = d.get('alias_candidates')
        if not isinstance(rows,list) or len(rows) > 8 or type(d.get('candidate_count')) is not int or d['candidate_count'] != len(rows): return False
        seen = set()
        for row in rows:
            if (not isinstance(row,dict) or set(row) != {'operator_id','candidate_only','labels'} or row['candidate_only'] is not True
                    or type(row['operator_id']) is not int or not 1 <= row['operator_id'] <= 2147483647 or row['operator_id'] in seen
                    or not isinstance(row['labels'],list) or not 1 <= len(row['labels']) <= 3): return False
            seen.add(row['operator_id']); fields = set()
            for label in row['labels']:
                if (not isinstance(label,dict) or set(label) != {'field','value','sha256'} or label['field'] not in ('name','fullName','russianName')
                        or label['field'] in fields or not re.fullmatch('[a-f0-9]{64}',str(label['sha256']))): return False
                fields.add(label['field'])
                value = label['value']
                if value is not None and (not isinstance(value,str) or len(value)>100 or not re.fullmatch(OPERATOR_ALIAS_LABEL_PATTERN,value,re.I)
                    or hashlib.sha256(value.encode()).hexdigest() != label['sha256']): return False
        return d.get('global_usage') == 'not_measured' and result['match_retained'].get('evidence_sha256') == hashlib.sha256(canonical(d)).hexdigest()
    except (KeyError,TypeError,ValueError,AttributeError): return False


def completed_owner_top500_current(command, data):
    p=OWNER_TOP500_CURRENT
    fields={'schema_version','state','captured_at_utc','database_snapshot','parent_receipt_sha256',
            'cohort_hotels','matched_current_pairs','rows','publication_admission','website_online_bridge_verified',
            'canonical_publication_binding_verified','supplier_calls','database_writes','mapping_writes','safe_to_write_now',
            'operation_id','source_sha','control_source_sha','cohort','target_operation_id','current_raw_sha256','no_replay','new_database_reads'}
    if (command.get('mode')!='match-retained-current' or command.get('cohort')!=p['cohort']
            or command.get('operation_id')!=p['operation_id'] or command.get('target_operation_id')!=p['parent_operation_id']
            or set(data)!=fields or data.get('parent_receipt_sha256')!=p['parent_receipt_sha256']
            or data.get('database_snapshot')!='repeatable-read-read-only'
            or any(type(data.get(k)) is not int or data[k]!=v for k,v in
                   (('schema_version',1),('cohort_hotels',len(p['pairs'])),('new_database_reads',1)))
            or any(data.get(k) is not False for k in
                   ('publication_admission','website_online_bridge_verified','canonical_publication_binding_verified'))
            or not re.fullmatch(r'[a-f0-9]{64}',str(data.get('current_raw_sha256','')))):return False
    captured=data.get('captured_at_utc')
    if (not isinstance(captured,str) or not re.fullmatch(r'\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(?:Z|\+00:00)',captured)
            or dt.datetime.fromisoformat(captured.replace('Z','+00:00')).utcoffset()!=dt.timedelta(0)):return False
    rows=data.get('rows')
    if not isinstance(rows,list) or len(rows)!=len(p['pairs']):return False
    for row,(anex,tv,rank) in zip(rows,p['pairs']):
        if (not isinstance(row,dict) or set(row)!={'anex_hotel_id','tv_hotel_id','owner_rank','state',
                                                 'effective_pair_present','target_present','target_active','target_eligible'}
                or any(type(row.get(k)) is not int or row[k]!=v for k,v in
                       (('anex_hotel_id',anex),('tv_hotel_id',tv),('owner_rank',rank)))
                or any(type(row.get(k)) is not bool for k in
                       ('effective_pair_present','target_present','target_active','target_eligible'))):return False
        present=row['target_present'];active=row['target_active'];eligible=row['target_eligible']
        if (active and not present) or (eligible and not active):return False
        state='target_missing' if not present else 'target_ineligible' if not eligible else 'matched_current' if row['effective_pair_present'] else 'expected_pair_unresolved'
        if row['state']!=state:return False
    return type(data.get('matched_current_pairs')) is int and data['matched_current_pairs']==sum(r['state']=='matched_current' for r in rows)


def completed_result(command, result, control_sha):
    if command.get('operation_id') == LOCAL7_REGISTRATION['operation_id'] or command.get('cohort') == LOCAL7_REGISTRATION['cohort']:
        return completed_local7_registration(command, result, control_sha)
    if command.get('mode') == LOCAL19_APPLY_MODE or command.get('operation_id') == LOCAL19_APPLY['operation_id'] or command.get('cohort') == LOCAL19_APPLY['cohort']:
        return completed_local19_apply(command, result, control_sha)
    if command.get('cohort') == LOCAL26_IDENTITY['cohort'] or command.get('operation_id') == LOCAL26_IDENTITY['operation_id']:
        return completed_local26_identity(command, result, control_sha)
    if command.get('mode') == LOCAL9_APPLY_MODE or command.get('operation_id') == LOCAL9_APPLY['operation_id'] or command.get('cohort') == LOCAL9_APPLY['cohort']:
        return completed_local9_apply(command, result, control_sha)
    if command.get('cohort') == LOCAL11_IDENTITY['cohort'] or command.get('operation_id') == LOCAL11_IDENTITY['operation_id']:
        return completed_local11_identity(command, result, control_sha)
    if command.get('cohort') == FUNSUN_RU_IDENTITY_SCOPE['cohort']:
        return completed_bg_identity(command, result, control_sha, FUNSUN_RU_IDENTITY_SCOPE)
    if command.get('cohort') == OPERATOR_ALIAS_AUDIT['cohort']:
        return completed_operator_alias_audit(command, result, control_sha)
    if command.get('cohort') == BG_IDENTITY_SCOPE['cohort']:
        return completed_bg_identity(command, result, control_sha)
    if command.get('cohort') in (BINDING_RECONCILIATION['cohort'], BINDING_RECONCILIATION_V2['cohort']):
        return completed_binding_reconciliation(command, result, control_sha)
    if command.get('cohort') == OPERATOR_BINDING['cohort']:
        return completed_operator_dictionary(command, result, control_sha)
    if command.get('cohort') == OPERATOR_BINDING_FULL['cohort']:
        return completed_operator_dictionary(command, result, control_sha, OPERATOR_BINDING_FULL)
    try:
        policy=continuation_policy(command.get('cohort'))
        data = result['match_retained']['data']
        if 'catalogue_sha256' in policy and (command.get('match_retained_catalogue_sha256')!=policy['catalogue_sha256']
                or data.get('continuation_catalogue_sha256')!=policy['catalogue_sha256']):return False
        if 'catalogue_sha256' in policy and command['mode']=='match-retained-acquire' and not re.fullmatch(r'[a-f0-9]{64}',str(data.get('catalogue_sibling_history_sha256',''))):return False
        if (command['mode'] not in MODES or result.get('status') != 'complete'
                or result.get('mode') != command['mode']
                or result.get('source_sha') != command['source_sha']
                or result.get('operation_id') != command['operation_id']
                or data.get('source_sha') != command['source_sha']
                or data.get('operation_id') != command['operation_id']
                or data.get('control_source_sha') != control_sha
                or data.get('cohort') != command['cohort']
                or data.get('no_replay') is not True or data.get('safe_to_write_now') is not False
                or result.get('production_unchanged') is not True):
            return False
        for obj in (data, result):
            keys=('supplier_calls',) if command['mode'] in ('match-retained-anex-write','match-retained-anex-write-v2') else ('database_writes','mapping_writes') if command['mode']=='match-retained-acquire' else ('supplier_calls','database_writes','mapping_writes')
            if any(type(obj.get(k)) is not int or obj[k] != 0 for k in keys):
                return False
        if command['mode'] in ('match-retained-readback','match-retained-project','match-retained-current','match-retained-acquire','match-retained-anex-write','match-retained-anex-write-v2') and data.get('target_operation_id')!=command.get('target_operation_id'):
            return False
        if (command.get('cohort')==OWNER_TOP500_CURRENT['cohort'] or command.get('operation_id')==OWNER_TOP500_CURRENT['operation_id']) and not completed_owner_top500_current(command,data):
            return False
        if command['mode']=='match-retained-acquire' and (type(data.get('supplier_calls')) is not int or not 0<=data['supplier_calls']<=400 or result.get('supplier_calls')!=data['supplier_calls']):return False
        if policy.get('scope_kind')=='never_selected_bounded' and command['mode']=='match-retained-acquire':
            if (data['supplier_calls']>30 or type(data.get('tariff_search_units')) is not int or not 0<=data['tariff_search_units']<=1
                    or type(data.get('physical_http_reservations')) is not int or data['physical_http_reservations']!=data['supplier_calls']
                    or data.get('context_origin')!=policy['context_origin'] or data.get('wire_context')!=policy['wire_context']
                    or not re.fullmatch(r'[a-f0-9]{64}',str(data.get('selection_reservation_sha256','')))):return False
        if command['mode'] in ('match-retained-anex-write','match-retained-anex-write-v2'):
            n=data.get('new_accepted_mappings')
            if type(n) is not int or not 0<=n<=(policy['selected_count'] if command['cohort']==policy['cohort'] else 7) or any(type(obj.get(k)) is not int or obj[k]!=n for obj in (data,result) for k in ('database_writes','mapping_writes')):return False
            if data.get('state')=='committed_verified' and (n<1 or len(data.get('rows',[]))!=n or data.get('readback_verified') is not True or data.get('resolver_readback_verified') is not True):return False
            if data.get('state') not in ('committed_verified','completed_no_eligible'):return False
            if data.get('state')=='completed_no_eligible' and n!=0:return False
        if command['cohort']==policy['cohort'] and (data.get('continuation_scope_sha256')!=policy['sha256'] or data.get('scope_id')!=policy['scope_id']):return False
        if command['operation_id'] in SAVED_DETAIL_READBACKS:
            p=SAVED_DETAIL_READBACKS[command['operation_id']]
            if (command['mode']!='match-retained-readback' or command['cohort']!=p['cohort']
                    or data.get('state')!='saved_detail_inspected_read_only' or data.get('tv_hotel_id')!=p['tv_hotel_id']
                    or data.get('raw_sha256')!=p['raw_sha256'] or data.get('source_acquisition_receipt_sha256')!=p['receipt_sha256']
                    or data.get('protected_inputs_unchanged') is not True
                    or data.get('protected_ledger_sha256',{}).get('hotel_selection')!=p['hotel_ledger_sha256']
                    or data.get('protected_ledger_sha256',{}).get('context_selection')!=p['context_ledger_sha256']
                    or not re.fullmatch(r'[a-f0-9]{64}',str(data.get('protected_ledger_sha256',{}).get('account_day','')))
                    or type(data.get('new_accepted_mappings')) is not int or data['new_accepted_mappings']!=0):return False
        if command['operation_id']==TRANSPORT_DIAGNOSTIC['operation_id']:
            if (command['mode']!='match-retained-readback' or command['cohort']!=policy['cohort']
                    or command.get('target_operation_id')!=TRANSPORT_DIAGNOSTIC['target_operation_id']
                    or data.get('failed_operation_receipt_sha256')!=TRANSPORT_DIAGNOSTIC['failed_receipt_sha256']
                    or data.get('failed_operation_status')!='unknown_no_replay'
                    or data.get('failed_operation_replayed') is not False
                    or data.get('protected_inputs_unchanged') is not True
                    or any(data.get(k) is not False for k in ('cause_established','historical_network_phase_recorded','metadata_signature_verified','raw_response_bodies_read'))
                    or data.get('config_metadata_is_current_not_historical') is not True
                    or type(data.get('new_accepted_mappings')) is not int or data['new_accepted_mappings']!=0
                    or type(data.get('known_lineage_http_attempts')) is not int
                    or data['known_lineage_http_attempts']!=sum(p['calls'] for p in TRANSPORT_DIAGNOSTIC['parents'])
                    or type(data.get('known_lineage_unknown')) is not int
                    or data['known_lineage_unknown']!=sum(p['unknown'] for p in TRANSPORT_DIAGNOSTIC['parents'])
                    or not isinstance(data.get('requests'),list) or len(data['requests'])!=data['known_lineage_http_attempts']):return False
        if command['operation_id']==PROOF_SOURCE_INSPECTION['operation_id']:
            p=PROOF_SOURCE_INSPECTION
            if (command['mode']!='match-retained-readback' or command['cohort']!=p['cohort']
                    or data.get('protected_inputs_unchanged') is not True or data.get('native_authority') is not False
                    or any(data.get(k)!=p[k] for k in ('capture_raw_sha256','capture_receipt_sha256','current_evidence_sha256','acquisition_receipt_sha256','remaining_id_sha256'))
                    or type(data.get('new_accepted_mappings')) is not int or data['new_accepted_mappings']!=0
                    or data.get('same_parent_ready')!=606 or data.get('same_parent_selected_ready')!=199
                    or data.get('same_parent_never_selected')!=407 or data.get('protected_inputs_count')!=16
                    or data.get('reference_projection',{}).get('native_authority') is not False
                    or data.get('reference_projection',{}).get('account_binding_authority') is not False
                    or data.get('result_structure',{}).get('native_authority') is not False
                    or any(not re.fullmatch(r'[a-f0-9]{64}',str(data.get(k,''))) for k in ('private_reference_projection_sha256','acquisition_result_raw_sha256'))
                    or not re.fullmatch(r'[a-f0-9]{64}',str(data.get('protected_ledger_sha256',{}).get('account_day','')))
                    or data.get('protected_ledger_sha256',{}).get('hotel_selection')!=p['hotel_ledger_sha256']
                    or data.get('protected_ledger_sha256',{}).get('context_selection')!=p['context_ledger_sha256']):return False
        expected = {'match-retained-intake':'captured_and_projected_read_only',
                    'match-tourvisor-quota-policy':'quota_policy_applied_no_supplier_calls',
                    'match-retained-readback':'retained_proof_sources_inspected_read_only' if command['operation_id']==PROOF_SOURCE_INSPECTION['operation_id'] else 'retained_transport_inspected_read_only' if command['operation_id']==TRANSPORT_DIAGNOSTIC['operation_id'] else 'saved_detail_inspected_read_only' if command['cohort']==policy['cohort'] else 'reconciled_read_only','match-retained-project':'reprojected_retained_read_only',
                    'match-retained-current':'current_retained_read_only',
                    'match-retained-acquire':'acquired_retained_native_read_only',
                    'match-retained-anex-write':data.get('state'),'match-retained-anex-write-v2':data.get('state')}[command['mode']]
        return (data.get('state')==expected and result['match_retained'].get('evidence_sha256')==
                hashlib.sha256(json.dumps(data,sort_keys=True,separators=(',',':')).encode()).hexdigest())
    except (KeyError, TypeError, AttributeError, ValueError, OSError):
        return False


def activate(core, command):
    if command.get('operation_id') == LOCAL7_REGISTRATION['operation_id'] or command.get('cohort') == LOCAL7_REGISTRATION['cohort']:
        return activate_local7_registration(core, command)
    if command.get('mode') == LOCAL19_APPLY_MODE or command.get('operation_id') == LOCAL19_APPLY['operation_id'] or command.get('cohort') == LOCAL19_APPLY['cohort']:
        return activate_local19_apply(core, command)
    if command.get('cohort') == LOCAL26_IDENTITY['cohort']:
        return activate_local26_identity(core, command)
    if command.get('mode') == LOCAL9_APPLY_MODE:
        return activate_local9_apply(core, command)
    if command.get('mode') not in MODES:
        return
    fields=('source_sha','mode','operation_id','cohort')+ (('target_operation_id',) if command.get('mode') in ('match-retained-readback','match-retained-project','match-retained-current','match-retained-acquire','match-retained-anex-write','match-retained-anex-write-v2') else ())
    expected=core.parse_command(core.PREFIX+' '.join(str(command.get(k,'')) for k in fields))
    need(command==expected,'retained_command_shape')
    control=os.environ.get('GITHUB_SHA','')
    need(re.fullmatch(r'[a-f0-9]{40}',control) is not None,'retained_control_sha')
    command['match_retained_control_sha']=control
    remote=core.REMOTE
    anchors={'definition':'def run_match942(stage, mode, offset, limit):\n',
             'dispatch':"    if mode=='match-tv942-write':\n",
             'collector':"    if mode not in ('reconcile',",
             'manifest':"    if not isinstance(files,dict) or len(files)<20: fail('manifest')"}
    for key, anchor in anchors.items():
        need(remote.count(anchor)==(2 if key=='collector' else 1),'retained_registration_drift_'+key)
    local11 = command.get('cohort') == LOCAL11_IDENTITY['cohort']
    reconciliation_v2 = command.get('cohort') == BINDING_RECONCILIATION_V2['cohort']
    reconciliation = command.get('cohort') == BINDING_RECONCILIATION['cohort'] or reconciliation_v2
    alias_audit = command.get('cohort') == OPERATOR_ALIAS_AUDIT['cohort']
    funsun_identity = command.get('cohort') == FUNSUN_RU_IDENTITY_SCOPE['cohort']
    bg_identity = command.get('cohort') == BG_IDENTITY_SCOPE['cohort']
    binding_full = command.get('cohort') == OPERATOR_BINDING_FULL['cohort']
    binding = command.get('cohort') == OPERATOR_BINDING['cohort'] or binding_full
    policy = FUNSUN_RU_IDENTITY_SCOPE if funsun_identity else OPERATOR_ALIAS_AUDIT if alias_audit else BG_IDENTITY_SCOPE if bg_identity else BINDING_RECONCILIATION_V2 if reconciliation_v2 else BINDING_RECONCILIATION if reconciliation else OPERATOR_BINDING_FULL if binding_full else OPERATOR_BINDING if binding else continuation_policy(command.get('cohort'))
    catalogue='catalogue_sha256' in policy
    handler = REMOTE_HANDLER
    if local11:
        handler = '\ndef run_match_retained(stage):\n    return current_local11_identity(stage)\n'
        handler += '\nLOCAL11_IDENTITY='+repr(LOCAL11_IDENTITY)+'\nLOCAL11_HASHES='+repr(LOCAL11_HASHES)+'\nLOCAL11_RUNNER='+repr(LOCAL11_RUNNER)+'\n'+LOCAL11_VALIDATOR+LOCAL11_HANDLER
    if alias_audit:
        anchor = '    config=project/\'config.php\';dbhelper=project/\'data/db-v1.php\''
        need(handler.count(anchor) == 1, 'alias_audit_dispatch_anchor')
        handler = handler.replace(anchor, "    if mode=='match-retained-readback':return retained_operator_alias_audit(stage)\n"+anchor, 1)
        handler += operator_alias_audit_handler()
    if reconciliation:
        anchor = '    config=project/\'config.php\';dbhelper=project/\'data/db-v1.php\''
        need(handler.count(anchor) == 1, 'binding_reconciliation_dispatch_anchor')
        handler = handler.replace(anchor, "    if mode=='match-retained-readback':return retained_binding_reconciliation(stage)\n"+anchor, 1)
        handler += binding_reconciliation_v2_handler() if reconciliation_v2 else BINDING_RECONCILIATION_HANDLER
    if binding:
        anchor = "    if mode=='match-retained-acquire':return acquire_retained_target(stage)"
        need(handler.count(anchor) == 1, 'operator_dictionary_dispatch_anchor')
        handler = handler.replace(anchor, "    if mode=='match-retained-acquire':return retained_operator_dictionary(stage)", 1)
        handler += full_operator_binding_handler() if binding_full else OPERATOR_BINDING_HANDLER
    if bg_identity:
        anchor = "    if mode=='match-retained-acquire':return acquire_retained_target(stage)"
        need(handler.count(anchor) == 1, 'bg_identity_dispatch_anchor')
        handler = handler.replace(anchor, "    if mode=='match-retained-acquire':return retained_bg_identity(stage)", 1)
        handler += BG_IDENTITY_HANDLER
    if funsun_identity:
        anchor = "    if mode=='match-retained-acquire':return acquire_retained_target(stage)"
        need(handler.count(anchor) == 1, 'funsun_ru_identity_dispatch_anchor')
        handler = handler.replace(anchor, "    if mode=='match-retained-acquire':return retained_funsun_ru_identity(stage)", 1)
        handler += funsun_ru_identity_handler()
    remote=remote.replace(anchors['definition'],'RETAINED_MODES='+repr(LEGACY_MODES)+'\nOWNER_TOP500_CURRENT='+repr(OWNER_TOP500_CURRENT)+'\nNEXT_SCOPE='+repr(policy)+'\nSAVED_DETAIL_READBACKS='+repr(SAVED_DETAIL_READBACKS)+'\nTRANSPORT_DIAGNOSTIC='+repr(TRANSPORT_DIAGNOSTIC)+'\nPROOF_SOURCE_INSPECTION='+repr(PROOF_SOURCE_INSPECTION)+'\n'+handler+'\n'+anchors['definition'],1)
    remote=remote.replace(anchors['dispatch'],REMOTE_DISPATCH+anchors['dispatch'],1)
    remote=remote.replace(anchors['collector'],"    if mode not in ('match-retained-intake','match-tourvisor-quota-policy','match-retained-readback','match-retained-project','match-retained-current','match-retained-acquire','match-retained-anex-write','match-retained-anex-write-v2','reconcile',")
    hashes=dict(LOCAL11_HASHES if local11 else SOURCE_HASHES)
    if catalogue:hashes[CATALOGUE_PATH]=policy['catalogue_sha256']
    remote=remote.replace(anchors['manifest'],"    if not isinstance(files,dict) or files!="+repr(hashes)+": fail('retained_manifest')",1)
    if local11:
        members='        members=package.getmembers()\n'
        need(remote.count(members)==1, 'local11_archive_anchor')
        remote=remote.replace(members,members+"        if len(members)!=3 or {m.name for m in members}!={*LOCAL11_HASHES,'manifest.json'}: fail('local11_archive_members')\n",1)
    reservation='    op.mkdir(mode=0o700)\n'
    receipt="if 'op' in globals() and op.exists():"
    need(remote.count(reservation)==1 and remote.count(receipt)==1,'retained_reservation_anchor')
    remote=remote.replace(reservation,reservation+'    retained_reserved=True\n',1)
    remote=remote.replace(receipt,"if globals().get('retained_reserved') is True and op.exists():",1)
    ast.parse(remote)
    core.REMOTE=remote;core.bundle_source=bundle_local11_source if local11 else lambda source_root:bundle_source(source_root,catalogue=catalogue)
    original=core.completed_result
    core.completed_result=lambda cmd,res: completed_result(cmd,res,control) if cmd.get('mode') in MODES else original(cmd,res)




# This finite profile has new raw bridge/manual/excluded evidence. It does not
# replay the consumed owner7/current profile or the official11 apply operation.
LOCAL11_RUNNER = 'scripts/diagnostics/hotel_match_local11_identity_v1.php'
LOCAL11_HASHES = {
    'v2/data/db-v1.php': 'c1c841d38c62845bc36e559183da17e0411c1c5bdd909517ffc02b5fbb1e2f13',
    LOCAL11_RUNNER: '60ac74200a678ada4e4e7d6561b31b9ef95568155c4f4be9e3f07906a403843a',
}
LOCAL11_IDENTITY = {
    'cohort': 'local11-identity-20261122',
    'operation_id': 'int-andromeda-match-retained-current-20261122-v1',
    'parent_operation_id': 'int-andromeda-local-official11-apply-20261121-v1',
    'parent_source_sha': '8b804957501d5b4715bd6f400fb794f02792d3de',
    'parent_control_sha': '458602aa945afec1c3d4a20434bae1945e2a6844',
    'plan_sha256': '57cadce752698c97269b174fa5a94afb254b1ec279ca87fd2ba32aa0a8bbca71',
    'owner_receipt_sha256': 'bc4cf1f52b0ad94c7ef3c25c24b6da277c633ee4726e5d9989ae4d92944dc865',
    'source_contract': {
        'own_namespace': 'anytour_hotels.id',
        'legacy_source_namespace': 'legacy_catalog',
        'target_namespace': 'local_tv_hotels.id=Tourvisor',
        'bridge_columns': ['local_tv_legacy_links.old_local_id', 'local_tv_legacy_links.tv_id'],
        'expected_own_manifest_sha256': '93e0ea33e64ce2c41d68ffb54e9efaf6e383f3cdde68f01c6aa4ab51f4e5a242',
        'native_catalogue_sha256': 'b262df970a51376ca3e34f5529809446781db45df4ba63ff1f2456970d071c47',
        'native_catalogue_commit': '586313554d03fd6fc96516ce0f1376988cd33819',
        'own_bridge_receipt': '4709/6918997216',
        'native_proof_receipt': '1971/6919021923',
    },
    # own ID, TV ID, canonical/native name, own country/region, TV country/region IDs.
    'pairs': [
        [5371,12120,'ANANTARA KIHAVAH VILLAS','Мальдивы','Мальдивы',8,42],
        [6014,64957,'KUDADOO MALDIVES PRIVATE ISLAND','Мальдивы','Мальдивы',8,42],
        [6202,76029,'THE RITZ CARLTON MALDIVES FARI ISLANDS','Мальдивы','Мальдивы',8,42],
        [5160,66198,'WALDORF ASTORIA MALDIVES ITHAAFUSHI','Мальдивы','Мальдивы',8,42],
        [5794,49750,'SONEVA JANI RESORT','Мальдивы','Мальдивы',8,42],
        [4516,2526,'BURJ AL ARAB','ОАЭ','Дубай',9,45],
        [7218,123247,'JUMEIRAH MARSA AL ARAB','ОАЭ','Дубай',9,45],
        [12008,48184,'JUMEIRAH AL NASEEM (EX. MADINAT JUMEIRAH AL NASEEM)','ОАЭ','Дубай',9,45],
        [5598,32322,'FOUR SEASONS RESORT DUBAI AT JUMEIRAH BEACH','ОАЭ','Дубай',9,45],
        [10771,28272,'ANANTARA PHUKET LAYAN RESORT & SPA','Таиланд','Пхукет',2,8],
        [8689,7458,'FOUR SEASONS THE NAM HAI','Вьетнам','Хой Ан',16,105],
    ],
}

# Shared strict observation/material validation runs in the remote and receiver.
LOCAL11_VALIDATOR = r'''
def l11_need(value,reason='local11_material'):
    if not value: raise ValueError(reason)
def l11_json(value):
    return json.dumps(value,sort_keys=True,separators=(',',':')).encode()
def l11_unique(pairs):
    result={}
    for key,value in pairs:
        l11_need(key not in result,'local11_duplicate_json_key');result[key]=value
    return result
def l11_hash(value):
    return isinstance(value,str) and re.fullmatch(r'[a-f0-9]{64}',value) is not None
def l11_int(value):
    return type(value) is int and 0<value<10**15
def l11_time(value):
    l11_need(isinstance(value,str) and len(value)<=32)
    stamp=__import__('datetime').datetime.fromisoformat(value.replace('Z','+00:00'))
    l11_need(stamp.utcoffset()==__import__('datetime').timedelta(0))
def l11_fields(value):
    l11_need(isinstance(value,list) and len(value)<=128
             and all(isinstance(k,str) and re.fullmatch(r'[a-zA-Z][a-zA-Z0-9_.]{0,95}',k) for k in value)
             and value==sorted(set(value)))
def l11_identity(value):
    l11_need(isinstance(value,dict) and set(value)=={'name','country','region','country_id','region_id'})
    for key in ('name','country','region'):
        item=value[key]
        l11_need(item is None or (isinstance(item,str) and len(item.encode())<=1024
            and not any(ord(c)<32 for c in item)))
    for key in ('country_id','region_id'):l11_need(value[key] is None or l11_int(value[key]))
def l11_observation(value,kind):
    fields={
      'own':{'own_id','revision','active','identity','profile_sha256','profile_integrity'},
      'source':{'own_id','namespace','external_key','acquired_via','source_sha256','source_integrity','source_identity_id','identity'},
      'link':{'own_id','tv_id','snapshot_sha256','snapshot_integrity','snapshot_own_id','snapshot_revision',
              'snapshot_profile_sha256','snapshot_profile_integrity','migration_issue_fields','migration_issues_sha256'},
      'target':{'tv_id','state','revision','discovery_identity','content_identity','manual_identity','manual_fields',
                'discovery_sha256','content_sha256','manual_sha256','source_absent_sha256','source_present',
                'source_sha256','source_integrity','source_identity_id'}}
    l11_need(isinstance(value,dict) and set(value)==fields[kind])
    for key in fields[kind] & {'own_id','tv_id','revision'}:l11_need(l11_int(value[key]))
    for key in fields[kind] & {'source_identity_id','snapshot_own_id','snapshot_revision'}:
        l11_need(value[key] is None or l11_int(value[key]))
    for key in fields[kind]:
        if key.endswith('_sha256'):
            nullable=key=='snapshot_profile_sha256' or (key=='source_sha256' and kind=='target' and not value['source_present'])
            l11_need((nullable and value[key] is None) or l11_hash(value[key]))
    for key in fields[kind] & {'active','profile_integrity','source_integrity','snapshot_integrity','snapshot_profile_integrity','source_present'}:
        l11_need((kind=='target' and key=='source_integrity' and not value['source_present'] and value[key] is None)
                 or type(value[key]) is bool)
    for key in fields[kind]:
        if key.endswith('identity') or key=='identity':l11_identity(value[key])
    if kind=='source':
        for key in ('namespace','acquired_via'):
            l11_need(isinstance(value[key],str) and re.fullmatch(r'[a-zA-Z0-9_.:-]{1,64}',value[key]))
        l11_need(isinstance(value['external_key'],str) and 0<len(value['external_key'].encode())<=128
                 and not any(ord(c)<32 for c in value['external_key']))
    if kind=='link':l11_fields(value['migration_issue_fields'])
    if kind=='target':
        l11_fields(value['manual_fields'])
        l11_need(value['state'] in ('pending','ready','retry','excluded','generic'))
        if not value['source_present']:
            l11_need(value['source_sha256'] is None and value['source_integrity'] is None and value['source_identity_id'] is None)
def l11_parent(prior,p):
    keys={'schema_version','operation_id','source_sha','mode','status','supplier_calls','database_writes',
          'booking_calls','lead_calls','production_before','production_after','production_unchanged',
          'profile_writes','provenance_writes','mapping_writes','legacy_writes','schema_writes','local_official11'}
    l11_need(isinstance(prior,dict) and set(prior)==keys,'local11_parent_shape')
    l11_need(type(prior['schema_version']) is int and prior['schema_version']==1
        and prior['operation_id']==p['parent_operation_id'] and prior['source_sha']==p['parent_source_sha']
        and prior['mode']=='local-official11-apply-v1' and prior['status']=='complete'
        and prior['production_unchanged'] is True,'local11_parent_terminal')
    before=prior['production_before']
    l11_need(isinstance(before,dict) and set(before)=={'index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'}
        and before==prior['production_after'] and all(v is None or l11_hash(v) for v in before.values()),'local11_parent_production')
    d=prior['local_official11']
    fields={'schema_version','operation_id','source_sha','control_sha','manifest_sha256','actions_sha256',
          'original_package_sha256','approval_ref','state','requested_profiles','requested_fields','plan_sha256',
          'profiles_updated','fields_updated','field_counts','preserved_fields','profile_writes','provenance_writes',
          'database_writes','supplier_calls','provider_http_calls','mapping_writes','legacy_writes','schema_writes',
          'readback_verified','consumer_readback_verified','consumer_sha256','owner_receipt_sha256',
          'protected_before','protected_after','error_code'}
    l11_need(isinstance(d,dict) and set(d)==fields,'local11_parent_material')
    expected={'operation_id':p['parent_operation_id'],'source_sha':p['parent_source_sha'],'control_sha':p['parent_control_sha'],
        'state':'committed_verified','manifest_sha256':p['source_contract']['expected_own_manifest_sha256'],
        'actions_sha256':'45e11557da29ecc652589e5cc89a6dcfa457b57e1a8936fe87d57d2ceb12edb4',
        'original_package_sha256':'01090549e278cbd739efa4936472c256724f6c555bd0d44864d6d21d2ce15123',
        'approval_ref':'https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7063885208',
        'plan_sha256':p['plan_sha256'],'owner_receipt_sha256':p['owner_receipt_sha256'],
        'consumer_sha256':'956f73539da2d6a25cd9c39770eb1e733b5a1c143fe2270856a833696aec7943',
        'readback_verified':True,'consumer_readback_verified':True,'error_code':None}
    l11_need(all(type(d[k]) is type(v) and d[k]==v for k,v in expected.items()),'local11_parent_pins')
    counts={'schema_version':1,'requested_profiles':11,'requested_fields':33,'profiles_updated':11,'fields_updated':33,
        'preserved_fields':0,'profile_writes':11,'provenance_writes':11,'database_writes':22,
        'supplier_calls':0,'provider_http_calls':0,'mapping_writes':0,'legacy_writes':0,'schema_writes':0}
    l11_need(all(type(d[k]) is int and d[k]==v for k,v in counts.items()),'local11_parent_counts')
    l11_need(isinstance(d['field_counts'],dict) and set(d['field_counts'])=={'description','primaryImage','images'}
        and all(type(v) is int and v==11 for v in d['field_counts'].values()) and l11_hash(d['consumer_sha256']),
        'local11_parent_readback')
    protected=d['protected_before']
    l11_need(isinstance(protected,dict) and set(protected)=={'config.php','data/db-v1.php'}
        and protected==d['protected_after'] and all(l11_hash(v) for v in protected.values())
        and protected['data/db-v1.php']==LOCAL11_HASHES['v2/data/db-v1.php'],'local11_parent_protected')
    outer={'supplier_calls':0,'booking_calls':0,'lead_calls':0,'mapping_writes':0,'legacy_writes':0,'schema_writes':0,
           'profile_writes':11,'provenance_writes':11,'database_writes':22}
    l11_need(all(type(prior[k]) is int and prior[k]==v for k,v in outer.items()),'local11_parent_outer_counts')
def l11_row_facts(row,pair):
    own,tv,name,country,region,country_id,region_id=pair
    l11_need(row['own_id']==own and type(row['own_id']) is int and row['tv_id']==tv and type(row['tv_id']) is int)
    reasons=[];a=row['own'];b=row['bridge'];t=row['target'];linked=row['linked_target']
    for value,kind,key,identity in ((a,'own','own_id',own),(b,'link','own_id',own),(t,'target','tv_id',tv)):
        if value is not None:l11_observation(value,kind);l11_need(value[key]==identity)
    if linked is not None:
        l11_observation(linked,'target');l11_need(b is not None and b['tv_id']!=tv and linked['tv_id']==b['tv_id'])
    for ids in (row['expected_legacy_owners'],row['target_link_owners']):
        l11_need(isinstance(ids,list) and len(ids)<=128 and all(l11_int(v) for v in ids) and ids==sorted(set(ids)))
    sources=row['own_sources']
    l11_need(isinstance(sources,list) and len(sources)<=256)
    for s in sources:l11_observation(s,'source');l11_need(s['own_id']==own)
    l11_need(len({(s['namespace'],s['external_key']) for s in sources})==len(sources))
    legacy=[s for s in sources if s['namespace']=='legacy_catalog']
    if a is None:reasons.append('own_missing')
    else:
        if not a['active']:reasons.append('own_inactive')
        if not a['profile_integrity']:reasons.append('own_profile_integrity_failed')
        if [a['identity'][k] for k in ('name','country','region')]!=[name,country,region]:reasons.append('own_identity_drift')
        if a['revision']<2:reasons.append('official11_revision_not_observed')
    if any(not s['source_integrity'] for s in sources):reasons.append('own_source_integrity_failed')
    if not legacy:reasons.append('legacy_source_absent')
    elif len(legacy)!=1:reasons.append('legacy_source_ambiguous')
    if any(s['external_key']!=str(tv) for s in legacy):reasons.append('legacy_source_target_differs')
    if any(s['source_identity_id']!=tv for s in legacy):reasons.append('legacy_source_identity_unconfirmed')
    if any(s['acquired_via']!='saved_catalog' for s in legacy):reasons.append('legacy_source_origin_unconfirmed')
    l11_need((own in row['expected_legacy_owners'])==any(s['external_key']==str(tv) for s in legacy))
    if any(v!=own for v in row['expected_legacy_owners']):reasons.append('legacy_target_owned_by_other_own')
    if any(v!=own for v in row['target_link_owners']):reasons.append('target_linked_to_other_own')
    if t is None:reasons.append('expected_target_absent')
    else:
        if t['state'] in ('excluded','generic'):reasons.append('expected_target_'+t['state'])
        if t['manual_fields']:reasons.append('target_manual_fields_present')
        if set(t['manual_fields'])&{'name','country','region','country.name','country.id','region.name','region.id'}:
            reasons.append('target_manual_identity_present')
        if not t['source_present']:reasons.append('target_source_absent')
        elif not t['source_integrity']:reasons.append('target_source_integrity_failed')
        if t['source_present'] and t['source_identity_id']!=tv:reasons.append('target_source_identity_unconfirmed')
        identities=[t[k] for k in ('discovery_identity','content_identity','manual_identity')]
        facts={'name':name,'country_id':country_id,'region_id':region_id}
        if any(i[k] is not None and i[k]!=value for i in identities for k,value in facts.items()):
            reasons.append('target_identity_differs_from_native_proof')
        if any(not any(i[k]==value for i in identities) for k,value in facts.items()):
            reasons.append('target_identity_incomplete')
    status='absent' if b is None else 'expected_target' if b['tv_id']==tv else 'different_target'
    relation='no_bridge'
    if b is None:reasons.append('bridge_absent');l11_need(linked is None and own not in row['target_link_owners'])
    else:
        l11_need((own in row['target_link_owners'])==(b['tv_id']==tv))
        if b['tv_id']!=tv:reasons.append('bridge_target_differs')
        if b['tv_id']==tv:l11_need(linked is None)
        if not b['snapshot_integrity'] or not b['snapshot_profile_integrity']:
            reasons.append('bridge_snapshot_integrity_failed');relation='invalid'
        elif b['snapshot_own_id']!=own:reasons.append('bridge_snapshot_own_differs');relation='identity_mismatch'
        elif a is None:relation='own_missing'
        elif b['snapshot_revision']==a['revision'] and b['snapshot_profile_sha256']==a['profile_sha256']:
            relation='same_profile'
        else:relation='historical_profile'
    actual=t if b is not None and b['tv_id']==tv else linked
    visibility='missing_link' if b is None else 'target_missing' if actual is None else 'target_excluded' if actual['state']=='excluded' else 'visible'
    return {'reasons':sorted(reasons),'bridge_status':status,'snapshot_profile_relation':relation,'reverse_visibility':visibility}
def l11_project(raw,p):
    keys={'schema_version','state','captured_at_utc','database_snapshot','own_rows','source_rows','link_rows','target_rows',
          'supplier_calls','provider_http_calls','database_writes','mapping_writes'}
    l11_need(isinstance(raw,dict) and set(raw)==keys,'local11_snapshot_shape')
    l11_need(type(raw['schema_version']) is int and raw['schema_version']==1
        and raw['state']=='local11_identity_snapshot' and raw['database_snapshot']=='repeatable-read-read-only')
    l11_time(raw['captured_at_utc'])
    for k in ('supplier_calls','provider_http_calls','database_writes','mapping_writes'):
        l11_need(type(raw[k]) is int and raw[k]==0,'local11_snapshot_writes')
    own_ids={r[0] for r in p['pairs']};tv_ids={r[1] for r in p['pairs']}
    for name,kind,limit in (('own_rows','own',11),('source_rows','source',256),('link_rows','link',128),('target_rows','target',22)):
        l11_need(isinstance(raw[name],list) and len(raw[name])<=limit)
        for value in raw[name]:l11_observation(value,kind)
    own={r['own_id']:r for r in raw['own_rows']};links={r['own_id']:r for r in raw['link_rows']}
    targets={r['tv_id']:r for r in raw['target_rows']};sources=raw['source_rows']
    l11_need(len(own)==len(raw['own_rows']) and set(own)<=own_ids)
    l11_need(len(links)==len(raw['link_rows']) and all(r['own_id'] in own_ids or r['tv_id'] in tv_ids for r in links.values()))
    l11_need(len(targets)==len(raw['target_rows']) and set(targets)<=tv_ids|{r['tv_id'] for r in links.values() if r['own_id'] in own_ids})
    l11_need(len({(r['namespace'],r['external_key']) for r in sources})==len(sources))
    l11_need(all(r['own_id'] in own_ids or (r['namespace']=='legacy_catalog' and r['external_key'] in {str(v) for v in tv_ids}) for r in sources))
    rows=[]
    for pair in p['pairs']:
        a,tv=pair[:2];b=links.get(a)
        row={'own_id':a,'tv_id':tv,'own':own.get(a),'bridge':b,'target':targets.get(tv),
            'linked_target':targets.get(b['tv_id']) if b is not None and b['tv_id']!=tv else None,
            'own_sources':sorted([r for r in sources if r['own_id']==a],key=lambda r:(r['namespace'],r['external_key'])),
            'expected_legacy_owners':sorted({r['own_id'] for r in sources if r['namespace']=='legacy_catalog' and r['external_key']==str(tv)}),
            'target_link_owners':sorted({r['own_id'] for r in links.values() if r['tv_id']==tv})}
        row.update(l11_row_facts(row,pair));rows.append(row)
    return {'state':'local11_identity_inspected_read_only','captured_at_utc':raw['captured_at_utc'],
        'database_snapshot':raw['database_snapshot'],'rows':rows,'source_contract':p['source_contract'],
        'scope_sha256':hashlib.sha256(l11_json(p['pairs'])).hexdigest(),
        'supplier_calls':0,'provider_http_calls':0,'database_writes':0,'mapping_writes':0,
        'safe_to_write_now':False,'publication_admission':False,'accepted_native_bindings':0}
def l11_validate_data(data,p):
    keys={'state','captured_at_utc','database_snapshot','rows','source_contract','scope_sha256',
        'supplier_calls','provider_http_calls','database_writes','mapping_writes','safe_to_write_now',
        'publication_admission','accepted_native_bindings','operation_id','source_sha','control_source_sha',
        'cohort','target_operation_id','parent_receipt_sha256','parent_semantic_pins',
        'current_raw_sha256','no_replay','new_database_reads'}
    l11_need(isinstance(data,dict) and set(data)==keys)
    l11_need(data['state']=='local11_identity_inspected_read_only' and data['database_snapshot']=='repeatable-read-read-only')
    l11_time(data['captured_at_utc'])
    l11_need(data['source_contract']==p['source_contract'] and data['scope_sha256']==hashlib.sha256(l11_json(p['pairs'])).hexdigest())
    l11_need(data['parent_semantic_pins']=={k:p[k] for k in ('parent_operation_id','parent_source_sha','parent_control_sha','plan_sha256','owner_receipt_sha256')})
    for k in ('supplier_calls','provider_http_calls','database_writes','mapping_writes','accepted_native_bindings'):
        l11_need(type(data[k]) is int and data[k]==0)
    l11_need(data['safe_to_write_now'] is False and data['publication_admission'] is False and data['no_replay'] is True
        and type(data['new_database_reads']) is int and data['new_database_reads']==1
        and l11_hash(data['current_raw_sha256']) and l11_hash(data['parent_receipt_sha256']))
    l11_need(isinstance(data['rows'],list) and len(data['rows'])==11)
    keys={'own_id','tv_id','own','bridge','target','linked_target','own_sources','expected_legacy_owners','target_link_owners',
          'reasons','bridge_status','snapshot_profile_relation','reverse_visibility'}
    for row,pair in zip(data['rows'],p['pairs']):
        l11_need(isinstance(row,dict) and set(row)==keys)
        l11_need(all(row[k]==v for k,v in l11_row_facts(row,pair).items()),'local11_derived_fact_drift')
'''
exec(LOCAL11_VALIDATOR)


def bundle_local11_source(source_root):
    root=Path(source_root).resolve();control=Path(__file__).resolve().parents[2];out=io.BytesIO()
    with tarfile.open(fileobj=out,mode='w:gz',format=tarfile.PAX_FORMAT) as archive:
        for relative,digest in LOCAL11_HASHES.items():
            path=(control if relative==LOCAL11_RUNNER else root)/relative
            need(path.is_file() and not path.is_symlink() and path.resolve()==path and path.stat().st_nlink==1,'local11_source_path')
            raw=path.read_bytes()
            need(0<len(raw)<=1048576 and hashlib.sha256(raw).hexdigest()==digest,'local11_source_drift')
            info=tarfile.TarInfo(relative);info.size=len(raw);info.mode=0o600;info.mtime=0
            archive.addfile(info,io.BytesIO(raw))
        raw=l11_json({'schema_version':1,'files':LOCAL11_HASHES})
        info=tarfile.TarInfo('manifest.json');info.size=len(raw);info.mode=0o600;info.mtime=0
        archive.addfile(info,io.BytesIO(raw))
    return out.getvalue(),dict(LOCAL11_HASHES)


LOCAL11_HANDLER = r'''
def current_local11_identity(stage):
    p=LOCAL11_IDENTITY
    if (mode!='match-retained-current' or operation!=p['operation_id'] or payload.get('cohort')!=p['cohort']
        or payload.get('target_operation_id')!=p['parent_operation_id']):fail('local11_scope')
    target=private/p['parent_operation_id'];parent=target/'result.json';reservation=target/'reservation.json'
    for path in (parent,reservation,project/'config.php'):
        if not safe_file(path,1048576) or path.resolve()!=path:fail('local11_private_path')
    parent_bytes=parent.read_bytes();parent_hash=hashlib.sha256(parent_bytes).hexdigest()
    prior=json.loads(parent_bytes,object_pairs_hook=l11_unique);l11_parent(prior,p)
    reserved=json.loads(reservation.read_bytes(),object_pairs_hook=l11_unique)
    if (not isinstance(reserved,dict) or set(reserved)!={'operation_id','source_sha','mode','reserved_at'}
        or reserved.get('operation_id')!=p['parent_operation_id'] or reserved.get('source_sha')!=p['parent_source_sha']
        or reserved.get('mode')!='local-official11-apply-v1' or not l11_int(reserved.get('reserved_at'))):
        fail('local11_parent_reservation')
    protected={path:hashlib.sha256(path.read_bytes()).hexdigest() for path in (parent,reservation,project/'config.php')}
    scope=l11_json({'pairs':[r[:2] for r in p['pairs']]})
    fd=os.open(op/'local11-scope.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:handle.write(scope);handle.flush();os.fsync(handle.fileno())
    env={k:os.environ[k] for k in ('HOME','PATH','LANG','LC_ALL') if k in os.environ}
    env.update(MATCH_RETAINED_DIR=str(op),MATCH_RETAINED_SOURCE_ROOT=str(stage))
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
    run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',
        '-d','memory_limit=256M','-d','disable_functions='+disabled,str(stage/LOCAL11_RUNNER)],
        cwd=project,env=env,capture_output=True,text=True,timeout=120)
    raw=run.stdout.encode()
    if len(raw)>1048576:fail('local11_output_bound')
    fd=os.open(op/'local11-raw.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:handle.write(raw);handle.flush();os.fsync(handle.fileno())
    if run.returncode or run.stderr.strip():fail('local11_child_failed_no_replay')
    data=l11_project(json.loads(raw,object_pairs_hook=l11_unique),p)
    if any(not safe_file(path,1048576) or path.resolve()!=path or hashlib.sha256(path.read_bytes()).hexdigest()!=digest
           for path,digest in protected.items()):fail('local11_protected_drift')
    data.update(operation_id=operation,source_sha=source,control_source_sha=payload['match_retained_control_sha'],
        cohort=p['cohort'],target_operation_id=p['parent_operation_id'],parent_receipt_sha256=parent_hash,
        parent_semantic_pins={k:p[k] for k in ('parent_operation_id','parent_source_sha','parent_control_sha','plan_sha256','owner_receipt_sha256')},
        current_raw_sha256=hashlib.sha256(raw).hexdigest(),no_replay=True,new_database_reads=1)
    l11_validate_data(data,p)
    evidence=l11_json(data)
    fd=os.open(op/'retained-evidence.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:handle.write(evidence);handle.flush();os.fsync(handle.fileno())
    return {'evidence_sha256':hashlib.sha256(evidence).hexdigest(),'data':data}
'''


def completed_local11_identity(command,result,control):
    try:
        keys={'schema_version','operation_id','source_sha','mode','status','supplier_calls','database_writes',
              'booking_calls','lead_calls','production_before','production_after','production_unchanged',
              'mapping_writes','match_retained'}
        if not isinstance(result,dict) or set(result)!=keys or type(result['schema_version']) is not int or result['schema_version']!=1:return False
        before=result['production_before']
        if (not isinstance(before,dict) or set(before)!={'index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'}
            or before!=result['production_after'] or any(v is not None and not l11_hash(v) for v in before.values())):return False
        p=LOCAL11_IDENTITY;data=result['match_retained']['data'];l11_validate_data(data,p)
        for k,v in {'mode':'match-retained-current','operation_id':p['operation_id'],'cohort':p['cohort'],
                    'target_operation_id':p['parent_operation_id']}.items():
            if command.get(k)!=v:return False
        if not re.fullmatch(r'[a-f0-9]{40}',str(command.get('source_sha',''))) or not re.fullmatch(r'[a-f0-9]{40}',str(control)):return False
        if (result.get('status')!='complete' or result.get('production_unchanged') is not True
            or data['control_source_sha']!=control or data['operation_id']!=command['operation_id']
            or data['source_sha']!=command['source_sha'] or data['cohort']!=command['cohort']
            or data['target_operation_id']!=command['target_operation_id']):return False
        if any(result.get(k)!=command[k] for k in ('mode','operation_id','source_sha')):return False
        if any(type(result.get(k)) is not int or result[k]!=0 for k in ('supplier_calls','database_writes','mapping_writes','booking_calls','lead_calls')):return False
        if set(result['match_retained'])!={'evidence_sha256','data'}:return False
        return result['match_retained']['evidence_sha256']==hashlib.sha256(l11_json(data)).hexdigest()
    except (ValueError,KeyError,TypeError,AttributeError,OverflowError):
        return False


# Owner approval 4709/7086996252 covers exactly these nine bridges and the
# already approved 27 manual values. The historical proposal bytes stay frozen;
# neither its earlier pending flags nor the read profile confer write authority.
LOCAL9_RUNNER = 'scripts/catalog/anytour_local9_bridge_apply_v1.php'
LOCAL9_PROPOSAL = 'scripts/catalog/anytour-local9-bridge-proposal-20261122.json'
LOCAL9_APPLY = {
    'mode': LOCAL9_APPLY_MODE,
    'cohort': 'local9-bridge-manual27-20261123',
    'operation_id': 'int-andromeda-local9-bridge-apply-20261123-v1',
    'source_sha': '3207266e17ed4e8244aaf28d05b962747fd4cd3c',
    'approval_ref': 'https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7086996252',
    'proposal_sha256': '4ffb86ff073be9b9b0d2f89bf29d508099a22f94e2e5bbdf03e3c3a218fedc55',
    'parent_operation_id': 'int-andromeda-match-retained-current-20261122-v1',
    'parent_source_sha': '3207266e17ed4e8244aaf28d05b962747fd4cd3c',
    'parent_control_sha': '23f702c75ebcd9293672d9478373322683678153',
    'parent_evidence_sha256': 'ab9a4c8f8c98c454c3cad9e1b81d55c871f622ed8f914880d913f660405fcfea',
    'parent_raw_sha256': '6b5e4cddac3f2a11ad1ec1376a041faf11f7277fbed9ec5125354a4d057e54e7',
    'pairs': [
        [5371,12120,'f6356e45bac53f8c2c7a88c4470efec7d09cda6d4a5cba69914383b03e03d651'],
        [6014,64957,'5fd49755df7d4abe7b9b2b45aefd7a3f532618005d4f93670421480048e52cc4'],
        [6202,76029,'574c75fc5369dd898416e52728371f2d3b5f8e98acd24c7fc20aec7e6fd58e33'],
        [5160,66198,'84e6876908884f8f173e1978b32de19c8d6049f66964d985a0a1dd84cd67cfa2'],
        [5794,49750,'41cb4867d5704205b687981fc91db531c903c6302351825f2547155b5eed8a67'],
        [7218,123247,'1abe89f5849383275c910bb9daaf74abb02d2af168415e22c6809d4e6861ed36'],
        [12008,48184,'47d2dd2b58cd23d619a361d8a9caa08cde76cb9fa1860b6e0c93f314331babb5'],
        [5598,32322,'8b4b6a722032d4af2a74e434ccd3f398c1c336eaac9962b28f3b1efecdc50ef5'],
        [10771,28272,'1b29726139bfb05effe46309f697b9d9d3f85dbd06344472c6060ec18beabd09'],
    ],
    'excluded_pairs': [[4516,2526],[8689,7458]],
}
LOCAL9_HASHES = {
    'v2/data/db-v1.php': SOURCE_HASHES['v2/data/db-v1.php'],
    LOCAL9_RUNNER: '072d3a554ae87bed84f754d8b5e2b85e31dbb5b6fdef44bcd18fb1d7fe0b8ae1',
    LOCAL9_PROPOSAL: LOCAL9_APPLY['proposal_sha256'],
}

LOCAL9_VALIDATOR = r'''
def l9_parent(prior):
    p=LOCAL9_APPLY
    command={'source_sha':p['parent_source_sha'],'mode':'match-retained-current',
        'operation_id':p['parent_operation_id'],'cohort':LOCAL11_IDENTITY['cohort'],
        'target_operation_id':LOCAL11_IDENTITY['parent_operation_id']}
    l11_need(completed_local11_identity(command,prior,p['parent_control_sha']),'local9_parent_terminal')
    material=prior['match_retained']
    l11_need(material['evidence_sha256']==p['parent_evidence_sha256']
        and material['data']['current_raw_sha256']==p['parent_raw_sha256'],'local9_parent_evidence')

def l9_terminal(value):
    keys={'schema_version','operation_id','state','completed_at_utc','proposal_sha256','approval_ref','no_replay',
        'supplier_calls','provider_http_calls','database_writes','mapping_writes','native_updates','manual_field_writes',
        'canonical_profile_writes','source_writes','new_target_inserts','accepted_native_bindings',
        'commit_acknowledged','readback_verified','before','after','checks','pairs','error'}
    p=LOCAL9_APPLY
    l11_need(isinstance(value,dict) and set(value)==keys,'local9_terminal_shape')
    l11_need(value['schema_version']=='anytour_local9_bridge_apply_v1'
        and value['operation_id']==p['operation_id'] and value['proposal_sha256']==p['proposal_sha256']
        and value['approval_ref']==p['approval_ref'] and value['no_replay'] is True,'local9_terminal_identity')
    l11_time(value['completed_at_utc'])
    for key in ('supplier_calls','provider_http_calls','canonical_profile_writes','source_writes','new_target_inserts','accepted_native_bindings'):
        l11_need(type(value[key]) is int and value[key]==0,'local9_forbidden_writes')
    state=value['state'];committed=state in ('committed_verified','post_commit_readback_unknown')
    unknown=state in ('commit_unknown','rollback_unknown')
    l11_need(committed or unknown or state in ('rejected_before_write','rolled_back'),'local9_terminal_state')
    for key,count in zip(('database_writes','mapping_writes','native_updates','manual_field_writes'),(18,9,9,27)):
        l11_need(value[key] is None if unknown else type(value[key]) is int and value[key]==(count if committed else 0),
            'local9_write_counts')
    l11_need(type(value['commit_acknowledged']) is bool and value['commit_acknowledged']==committed
        and type(value['readback_verified']) is bool and value['readback_verified']==(state=='committed_verified'),
        'local9_commit_semantics')
    for name in ('before','after'):
        hashes=value[name]
        l11_need(hashes is None or isinstance(hashes,dict) and set(hashes)=={
            'canonical_rows_sha256','target_preserved_rows_sha256','excluded_rows_sha256'}
            and all(l11_hash(v) for v in hashes.values()),'local9_row_digests')
    checks=value['checks']
    l11_need(isinstance(checks,dict) and set(checks)=={'fresh_parent_match','canonical_unchanged',
        'target_preserved','excluded_unchanged','direct_tv_sql','own_to_tv_sql','snapshots_verified'},'local9_checks')
    for name,v in checks.items():
        l11_need(type(v) is bool or name in ('canonical_unchanged','target_preserved','excluded_unchanged') and v is None,
            'local9_checks')
    pairs=value['pairs']
    l11_need(isinstance(pairs,list) and len(pairs) in (0,9),'local9_pair_count')
    for row,expected in zip(pairs,p['pairs']):
        l11_need(isinstance(row,dict) and set(row)=={'own_id','tv_id','revision_from','revision_to','manual_fields',
            'manual_sha256','snapshot_sha256','direct_tv_sql','own_to_tv_sql'},'local9_pair_shape')
        for name,want in zip(('own_id','tv_id','revision_from','revision_to'),(*expected[:2],1,2)):
            l11_need(type(row[name]) is int and row[name]==want,'local9_pair_identity')
        l11_need(row['manual_fields']==['description','primaryImage','images'] and row['manual_sha256']==expected[2]
            and l11_hash(row['snapshot_sha256']) and type(row['direct_tv_sql']) is bool
            and type(row['own_to_tv_sql']) is bool,'local9_pair_evidence')
    if state=='committed_verified':
        l11_need(value['before'] is not None and value['before']==value['after'] and len(pairs)==9
            and all(v is True for v in checks.values()) and all(r['direct_tv_sql'] and r['own_to_tv_sql'] for r in pairs)
            and value['error'] is None,'local9_committed_readback')
    else:
        error=value['error']
        l11_need(isinstance(error,dict) and set(error)=={'code','message_sha256'}
            and isinstance(error['code'],str) and re.fullmatch(r'[a-z0-9_]{1,96}',error['code'])
            and l11_hash(error['message_sha256']),'local9_failure_evidence')
    return value

def l9_validate_data(data,command,control):
    keys={'operation_id','source_sha','control_source_sha','cohort','target_operation_id','approval_ref','proposal_sha256',
        'parent_receipt_sha256','parent_evidence_sha256','raw_sha256','no_replay','child_exit_code','child_stderr_sha256',
        'protected_before','protected_after','protected_inputs_unchanged','terminal'}
    l11_need(isinstance(data,dict) and set(data)==keys,'local9_data_shape')
    p=LOCAL9_APPLY
    for key,want in {'operation_id':p['operation_id'],'source_sha':p['source_sha'],'cohort':p['cohort'],
        'target_operation_id':p['parent_operation_id'],'approval_ref':p['approval_ref'],'proposal_sha256':p['proposal_sha256'],
        'parent_evidence_sha256':p['parent_evidence_sha256'],'control_source_sha':control}.items():
        l11_need(data[key]==want,'local9_data_binding')
    l11_need(all(command.get(k)==p[k] for k in ('operation_id','source_sha','cohort','mode'))
        and command.get('target_operation_id')==p['parent_operation_id']
        and re.fullmatch(r'[a-f0-9]{40}',str(control)) is not None,'local9_command_binding')
    l11_need(data['no_replay'] is True and l11_hash(data['parent_receipt_sha256']) and l11_hash(data['raw_sha256'])
        and (data['child_stderr_sha256'] is None or l11_hash(data['child_stderr_sha256'])),'local9_data_evidence')
    terminal=l9_terminal(data['terminal'])
    l11_need(type(data['child_exit_code']) is int and data['child_exit_code']==(0 if terminal['state']=='committed_verified' else 1),
        'local9_child_exit')
    before=data['protected_before']
    l11_need(isinstance(before,dict) and set(before)=={'parent/result.json','parent/reservation.json','config.php','data/db-v1.php'}
        and all(l11_hash(v) for v in before.values()) and data['protected_after']==before
        and before['parent/result.json']==data['parent_receipt_sha256']
        and before['data/db-v1.php']==LOCAL9_HASHES['v2/data/db-v1.php']
        and data['protected_inputs_unchanged'] is True,'local9_protected_inputs')
'''
exec(LOCAL9_VALIDATOR)


def bundle_local9_source(source_root):
    root=Path(source_root).resolve();control=Path(__file__).resolve().parents[2];out=io.BytesIO()
    with tarfile.open(fileobj=out,mode='w:gz',format=tarfile.PAX_FORMAT) as archive:
        for relative,digest in LOCAL9_HASHES.items():
            path=(root if relative=='v2/data/db-v1.php' else control)/relative
            need(path.is_file() and not path.is_symlink() and path.resolve()==path and path.stat().st_nlink==1,'local9_source_path')
            raw=path.read_bytes()
            need(0<len(raw)<=1048576 and hashlib.sha256(raw).hexdigest()==digest,'local9_source_drift')
            info=tarfile.TarInfo(relative);info.size=len(raw);info.mode=0o600;info.mtime=0
            archive.addfile(info,io.BytesIO(raw))
        raw=l11_json({'schema_version':1,'files':LOCAL9_HASHES})
        info=tarfile.TarInfo('manifest.json');info.size=len(raw);info.mode=0o600;info.mtime=0
        archive.addfile(info,io.BytesIO(raw))
    return out.getvalue(),dict(LOCAL9_HASHES)


LOCAL9_HANDLER = r'''
def apply_local9_bridge(stage):
    p=LOCAL9_APPLY
    if (mode!=p['mode'] or operation!=p['operation_id'] or source!=p['source_sha']
        or payload.get('cohort')!=p['cohort'] or payload.get('target_operation_id')!=p['parent_operation_id']):
        fail('local9_fixed_write_scope')
    target=private/p['parent_operation_id']
    paths={'parent/result.json':target/'result.json','parent/reservation.json':target/'reservation.json',
        'config.php':project/'config.php','data/db-v1.php':project/'data/db-v1.php'}
    def guarded_hashes():
        out={}
        for name,path in paths.items():
            if not safe_file(path,1048576) or path.resolve()!=path or path.stat().st_nlink!=1:fail('local9_protected_path')
            out[name]=hashlib.sha256(path.read_bytes()).hexdigest()
        return out
    protected=guarded_hashes()
    if protected['data/db-v1.php']!=LOCAL9_HASHES['v2/data/db-v1.php']:fail('local9_installed_db_helper_drift')
    prior=json.loads(paths['parent/result.json'].read_bytes(),object_pairs_hook=l11_unique);l9_parent(prior)
    reserved=json.loads(paths['parent/reservation.json'].read_bytes(),object_pairs_hook=l11_unique)
    if (not isinstance(reserved,dict) or set(reserved)!={'operation_id','source_sha','mode','reserved_at'}
        or reserved.get('operation_id')!=p['parent_operation_id'] or reserved.get('source_sha')!=p['parent_source_sha']
        or reserved.get('mode')!='match-retained-current' or not l11_int(reserved.get('reserved_at'))):
        fail('local9_parent_reservation')
    def write_once(name,raw):
        fd=os.open(op/name,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
        with os.fdopen(fd,'wb') as handle:handle.write(raw);handle.flush();os.fsync(handle.fileno())
    write_once('local9-child-reservation.json',l11_json({'operation_id':operation,'source_sha':source,
        'control_source_sha':payload['match_retained_control_sha'],'approval_ref':p['approval_ref'],
        'proposal_sha256':p['proposal_sha256'],'parent_evidence_sha256':p['parent_evidence_sha256'],'no_replay':True}))
    env={k:os.environ[k] for k in ('HOME','PATH','LANG','LC_ALL') if k in os.environ}
    env.update(MATCH_RETAINED_DIR=str(op),MATCH_RETAINED_SOURCE_ROOT=str(stage))
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
    result['database_writes']=result['mapping_writes']=None
    try:
        run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',
            '-d','memory_limit=256M','-d','disable_functions='+disabled,str(stage/LOCAL9_RUNNER)],
            cwd=project,env=env,capture_output=True,text=False,timeout=120)
    except subprocess.TimeoutExpired as exc:
        raw=exc.stdout or b''
        if isinstance(raw,str):raw=raw.encode()
        write_once('local9-raw.json',raw[:1048576])
        fail('local9_child_timeout_no_replay')
    raw=run.stdout
    write_once('local9-raw.json',raw[:1048576])
    if len(raw)>1048576:fail('local9_output_bound_no_replay')
    terminal=l9_terminal(json.loads(raw,object_pairs_hook=l11_unique))
    result['database_writes']=terminal['database_writes'];result['mapping_writes']=terminal['mapping_writes']
    after=guarded_hashes()
    data={'operation_id':operation,'source_sha':source,'control_source_sha':payload['match_retained_control_sha'],
        'cohort':p['cohort'],'target_operation_id':p['parent_operation_id'],'approval_ref':p['approval_ref'],
        'proposal_sha256':p['proposal_sha256'],'parent_receipt_sha256':protected['parent/result.json'],
        'parent_evidence_sha256':p['parent_evidence_sha256'],'raw_sha256':hashlib.sha256(raw).hexdigest(),
        'no_replay':True,'child_exit_code':run.returncode,
        'child_stderr_sha256':hashlib.sha256(run.stderr).hexdigest() if run.stderr else None,
        'protected_before':protected,'protected_after':after,'protected_inputs_unchanged':protected==after,'terminal':terminal}
    evidence=l11_json(data);write_once('retained-evidence.json',evidence)
    material={'evidence_sha256':hashlib.sha256(evidence).hexdigest(),'data':data}
    result['match_retained']=material
    l9_validate_data(data,payload,payload['match_retained_control_sha'])
    if run.stderr:fail('local9_child_stderr_no_replay')
    return material
'''

LOCAL9_DISPATCH = r'''    if mode==LOCAL9_APPLY['mode']:
        result['supplier_calls']=result['database_writes']=result['mapping_writes']=0
        result['match_retained']=apply_local9_bridge(stage)
        terminal=result['match_retained']['data']['terminal']
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True
        result['status']=('complete' if terminal['state']=='committed_verified' else
            'rolled_back_no_replay' if terminal['state']=='rolled_back' else
            'rejected_no_replay' if terminal['state']=='rejected_before_write' else 'unknown_no_replay')
'''


def completed_local9_apply(command,result,control):
    try:
        keys={'schema_version','operation_id','source_sha','mode','status','supplier_calls','database_writes',
            'booking_calls','lead_calls','production_before','production_after','production_unchanged','mapping_writes','match_retained'}
        if not isinstance(result,dict) or set(result)!=keys or type(result['schema_version']) is not int or result['schema_version']!=1:return False
        if result['status']!='complete' or result['production_unchanged'] is not True:return False
        if any(result.get(k)!=command.get(k) for k in ('operation_id','source_sha','mode')):return False
        before=result['production_before']
        if (not isinstance(before,dict) or set(before)!={'index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'}
            or before!=result['production_after'] or any(v is not None and not l11_hash(v) for v in before.values())):return False
        material=result['match_retained']
        if not isinstance(material,dict) or set(material)!={'evidence_sha256','data'}:return False
        data=material['data'];l9_validate_data(data,command,control)
        if data['terminal']['state']!='committed_verified' or data['child_stderr_sha256'] is not None:return False
        for name,want in {'supplier_calls':0,'database_writes':18,'mapping_writes':9,'booking_calls':0,'lead_calls':0}.items():
            if type(result[name]) is not int or result[name]!=want:return False
        return material['evidence_sha256']==hashlib.sha256(l11_json(data)).hexdigest()
    except (ValueError,KeyError,TypeError,AttributeError,OverflowError):
        return False


def activate_local9_apply(core,command):
    fields=('source_sha','mode','operation_id','cohort','target_operation_id')
    expected=core.parse_command(core.PREFIX+' '.join(str(command.get(k,'')) for k in fields))
    need(command==expected,'local9_command_shape')
    control=os.environ.get('GITHUB_SHA','')
    need(re.fullmatch(r'[a-f0-9]{40}',control) is not None,'local9_control_sha')
    command['match_retained_control_sha']=control
    remote=core.REMOTE
    anchors={'definition':'def run_match942(stage, mode, offset, limit):\n',
        'dispatch':"    if mode=='match-tv942-write':\n",'collector':"    if mode not in ('reconcile',",
        'manifest':"    if not isinstance(files,dict) or len(files)<20: fail('manifest')",
        'members':'        members=package.getmembers()\n','reservation':'    op.mkdir(mode=0o700)\n',
        'receipt':"if 'op' in globals() and op.exists():"}
    for key,anchor in anchors.items():
        need(remote.count(anchor)==(2 if key=='collector' else 1),'local9_registration_drift_'+key)
    handler='\nLOCAL11_IDENTITY='+repr(LOCAL11_IDENTITY)+'\nLOCAL11_HASHES='+repr(LOCAL11_HASHES)+'\n'+LOCAL11_VALIDATOR
    handler+='\n'+inspect.getsource(completed_local11_identity)
    handler+='\nLOCAL9_APPLY='+repr(LOCAL9_APPLY)+'\nLOCAL9_HASHES='+repr(LOCAL9_HASHES)+'\nLOCAL9_RUNNER='+repr(LOCAL9_RUNNER)+'\n'
    handler+=LOCAL9_VALIDATOR+LOCAL9_HANDLER
    remote=remote.replace(anchors['definition'],handler+'\n'+anchors['definition'],1)
    remote=remote.replace(anchors['dispatch'],LOCAL9_DISPATCH+anchors['dispatch'],1)
    remote=remote.replace(anchors['collector'],"    if mode not in ('local9-bridge-apply-v1','reconcile',")
    remote=remote.replace(anchors['manifest'],"    if not isinstance(files,dict) or files!="+repr(LOCAL9_HASHES)+": fail('local9_manifest')",1)
    remote=remote.replace(anchors['members'],anchors['members']+"        if len(members)!=4 or {m.name for m in members}!={*LOCAL9_HASHES,'manifest.json'}: fail('local9_archive_members')\n",1)
    remote=remote.replace(anchors['reservation'],anchors['reservation']+'    local9_reserved=True\n',1)
    remote=remote.replace(anchors['receipt'],"if globals().get('local9_reserved') is True and op.exists():",1)
    ast.parse(remote)
    core.REMOTE=remote;core.bundle_source=bundle_local9_source
    original=core.completed_result
    core.completed_result=lambda cmd,res:completed_local9_apply(cmd,res,control) if cmd.get('mode')==LOCAL9_APPLY_MODE else original(cmd,res)



# Finite LOCAL26 receiving measurement. Earlier before-pins are historical;
# current canonical values and every bridge leg are observed without writes.
LOCAL26_RUNNER = 'scripts/diagnostics/hotel_match_local26_identity_v1.php'
LOCAL26_MANIFEST = 'scripts/catalog/anytour-local-official26-approved-20261122.json'
LOCAL26_IDENTITY = {'cohort': 'local26-identity-20261123',
 'operation_id': 'int-andromeda-match-retained-current-20261123-v1',
 'source_sha': '3207266e17ed4e8244aaf28d05b962747fd4cd3c',
 'parent_operation_id': 'int-andromeda-local-official26-apply-20261122-v1',
 'parent_source_sha': '44515801b01f536e571fc3444276e1dd4bbdcd0b',
 'parent_control_sha': '2c355cc953d436a676af111bed71b90de0262af1',
 'parent_canonical_sha256': '82eebab327b6e43bb9c3342d4b6696d7769632b65eb956af1516509ef31fbd55',
 'plan_sha256': '894344b944883c813193837c5a60718c4bb00a8c4502c5679af6beeb450bac1e',
 'owner_receipt_sha256': 'b341e27bda6744c82ae173c4d7e33d81641b5674480f30e4c6b433a8b5b68b66',
 'consumer_sha256': 'bb0fdc084deb15e4eb278bb602f9d1612a332691be14578d95fdb39b8537ee5a',
 'approved_manifest_sha256': '7843b25c32c7adb8fa88f262aa870d8a469727ac3681f038ae418b98c0375138',
 'actions_sha256': 'd3e1c159a107638e069b5e6e003bf9ad085e2188be93ba1a2c78f75080db7c4e',
 'source_contract': {'own_namespace': 'anytour_hotels.id',
                     'legacy_source_namespace': 'legacy_catalog',
                     'candidate_target_namespace': 'local_tv_hotels.id=Tourvisor',
                     'bridge_columns': ['local_tv_legacy_links.old_local_id', 'local_tv_legacy_links.tv_id'],
                     'receiving_handoff': '4709/7087129483',
                     'read_scope_claim': '4709/7087404248',
                     'native_binding_admission': False},
 'pairs': [[4511, 2490, 'VILAMENDHOO ISLAND RESORT', 'Мальдивы', 'Мальдивы'],
           [10334, 22940, 'ANANTARA SIR BANI YAS ISLAND AL YAMM VILLA RESORT', 'ОАЭ', 'Абу-Даби'],
           [8751, 7831, 'ANANTARA BANGKOK RIVERSIDE RESORT & SPA', 'Таиланд', 'Бангкок'],
           [4679, 4283, 'ANANTARA SIAM BANGKOK (EX. FOUR SEASONS HOTEL BANGKOK)', 'Таиланд', 'Бангкок'],
           [14397, 97001, 'FOUR SEASONS HOTEL ALEXANDRIA AT SAN STEFANO', 'Египет', 'Александрия'],
           [13766, 74509, 'FOUR SEASONS HOTEL CAIRO AT NILE PLAZA', 'Египет', 'Каир'],
           [7599, 2458, 'KOMANDOO ISLAND RESORT', 'Мальдивы', 'Мальдивы'],
           [14932, 113544, 'ANANTARA SANTORINI ABU DHABI RETREAT', 'ОАЭ', 'Абу-Даби'],
           [12248, 52337, 'FOUR SEASONS HOTEL ABU DHABI AT AL MARYAH ISLAND', 'ОАЭ', 'Абу-Даби'],
           [4609, 2948, 'ANANTARA RESORT & SPA HUA HIN', 'Таиланд', 'Хуа Хин'],
           [12520, 57536, 'AL BALEED RESORT SALALAH BY ANANTARA', 'Оман', 'Салала'],
           [12839, 61241, 'ANANTARA AL JABAL AL AKHDAR RESORT', 'Оман', 'Маскат'],
           [13686, 73477, 'SIYAM WORLD', 'Мальдивы', 'Мальдивы'],
           [13066, 64648, 'FOUR SEASONS DUBAI INTERNATIONAL FINANCIAL CENTRE', 'ОАЭ', 'Дубай'],
           [4533, 2588, 'JUMEIRAH BEACH HOTEL', 'ОАЭ', 'Дубай'],
           [7619, 2611, 'JUMEIRAH AL QASR (EX. MADINAT JUMEIRAH AL QASR)', 'ОАЭ', 'Дубай'],
           [9843, 17424, 'FOUR SEASONS HOTEL ISTANBUL AT THE BOSPHORUS', 'Турция', 'Стамбул'],
           [9842, 17423, 'FOUR SEASONS AT SULTANAHMET', 'Турция', 'Стамбул'],
           [10377, 23718, 'ANANTARA HOI AN RESORT (EX. LIFE HERITAGE RESORT)', 'Вьетнам', 'Хой Ан'],
           [4172, 80008, 'HILTON MALDIVES AMINGIRI', 'Мальдивы', 'Мальдивы'],
           [4539, 2612, 'JUMEIRAH MINA AL SALAM (EX. MADINAT JUMEIRAH MINA A SALAM HOTEL)', 'ОАЭ', 'Дубай'],
           [4538, 2610, 'JUMEIRAH DAR AL MASYAF', 'ОАЭ', 'Дубай'],
           [4601, 2931, 'JUMEIRAH ZABEEL SARAY', 'ОАЭ', 'Дубай'],
           [14029, 78669, 'FOUR SEASONS HOTEL BANGKOK AT CHAO PHRAYA RIVER', 'Таиланд', 'Бангкок'],
           [12816, 60788, 'FOUR SEASONS HOTEL CAIRO AT THE FIRST RESIDENCE', 'Египет', 'Каир'],
           [14013, 78456, 'JUMEIRAH MUSCAT BAY', 'Оман', 'Маскат']],
 'expected_fields': {'4511': {'description': '9babd0fd0d9ccf47811bb4cea812f0a0823b1c59477a24fb4cb1c5628476a744',
                              'primaryImage': '18a26a4e2b085766aff2bed4fcdaf417774874fcbff58bfcfc8e3d4b7fa6cf90',
                              'images': 'b81b89086d9ea602ce0ec41148f4a56878efe4bace0361a92cc66e77cda305e1'},
                     '10334': {'description': 'e745d64bc5a8c6d27e8f8a33974b185da803e247bcbdaff0f47c532f991a325b',
                               'primaryImage': 'e76864f02bf1ec7e4679d67f31e06c34494bb52242dbfebf6ef10636b51411cd',
                               'images': 'f1ca168c64f3683b29cf57c45b2639fd031ccdd1f42b6861ec7c65485f411d22'},
                     '8751': {'description': '1467b9281b93d62c093cef3d6bc7b1ee336e252ca6167f0e576bae2121bc8439',
                              'primaryImage': '7e6755414df64f5cae06fa86a650f85b9d3becc206a8672c78b53c3ba09c90ab',
                              'images': '9a8e4db039bc7effd3e60a2bc52ff9242b25e1ae0770f147034292a2df82e907'},
                     '4679': {'description': '520f7768333106492618d944af5a13026df14af7527b7dba4e422df3f8bb261f',
                              'primaryImage': '14de21667318eed8a2258bdc9a2ff437bc16f24d576a5750fc72d9df6d491229',
                              'images': '75766f0bf0155261476e8795c9ecf6e96a467c9a6b2e44af1988050629c7d824'},
                     '14397': {'description': 'af0aea0aee2f71f82dfa258751dded4069053304dcbcb52f709d604d6241c743',
                               'primaryImage': 'c577e1974f8b45a5ddceeb565b2f32543e5d5e63fffb4d17fdd9e580e0fc9f02',
                               'images': 'c09c8c6e137f3d0e6c9152923e1133899565f58fb70783bcd7b9199542e3e574'},
                     '13766': {'description': '8e2e98176bee9317d88cf80ef52d6531f52b3a8d2733d47ed7e532683cd79ae5',
                               'primaryImage': '1daf580e1afec21627f1368c90584472a157e0581361bebbcbf27a1389d7ec7d',
                               'images': '16830a723c0a7b10f30cb168001c06897955350b11323d89a13527c21b0b07b8'},
                     '7599': {'description': 'e2a592dce62c6d98381fac1052f9d0c006a8b672b5dfd99fbaefa2cd1b64c88d',
                              'primaryImage': '1f1d1655c7ef004047ef10b5d0917c6f6ee6b00a19ce437770c955a1696d6876',
                              'images': '221dcacae0ac2600a881b8654ac6821b3fb979d7b79926f4359cb6a456ead3b0'},
                     '14932': {'description': '73a85a18f684ffd2139d383f39f55b7f7673a6dc97076ac968f4427a9cebebf2',
                               'primaryImage': 'd08d45f17d633cb9a678b64b3120cd34315b46396d32da4cf97d7324bc267e98',
                               'images': 'aedde6b27b45961b097025f0d4671e14c1306809d312ead97471c5cfe5de60ce'},
                     '12248': {'description': '97050741b6cfcf1887083570033949d5e7eaf915b0e8581b996b0db1e67b5728',
                               'primaryImage': 'a0ecf0446721dba0d462c733dad58c5e7ec043f05280bd3e9420d6fc96a0c501',
                               'images': '258b8667378b37f0039c93b78ec86800c3fdbc3ccb58ea26a5b83b9ae4999d30'},
                     '4609': {'description': '1e97a428c23875d6afdf8b55d7dfcf6c1b75a3ab605d829e1f5a710d3095723e',
                              'primaryImage': 'c60e528553fe1b063824ca37bd006566294ea109626c8a185a19d86416e0bee1',
                              'images': '85d404699084e16a2e6b7298418f51b7519a742444f12cd30043318bab13856e'},
                     '12520': {'description': '790d46bb018cabd741a298aeb06efc53d8d4b29939d4c7bc3d91243c65844f40',
                               'primaryImage': 'f51d91325928fdad7e39c370ac929a7a0b279740906fe191ee2a3529acf7cfcd',
                               'images': 'ecc96a3fe2b4d857d5d0d493bccd9a3fd7a55b7cf03f82aa4de46446d6ae6500'},
                     '12839': {'description': 'dd3379fb63a3c2753c2608cc8cfb2186e215ccdfde593ce540fc3cd042b04dd5',
                               'primaryImage': 'd4d0ed97cd77adc8c0f0537f6e22a8f7140dbaa05f5f863e90f6557ea3a823e4',
                               'images': '91f982a9b59e57e7a1283c2aaa93f3f194df79c3659012bebaaa3488d2cef75c'},
                     '13686': {'description': '9e4ed44913bf227ac63081c517ca01c37a2e26e752f96ca9986596eecf19ebe2',
                               'primaryImage': 'a3d49d579cb6e671e5f7cfdf2713d851849f918cbb568e56d4d89b555565a092',
                               'images': 'adf4deabd669db9b5ac0b1f2e11fe680a63d54da3be85012d6147a04c4a0179f'},
                     '13066': {'description': '1e6e816c95728be8d0f02b42b1070cf81034b5525acc3dc47cdbdf15f7c40b7c',
                               'primaryImage': '7f2b74aef416f74d21fbfe66b325e691dd759c7ae40d5f8576cd1af8c0a3547d',
                               'images': '6fa6131da41ef0bce1978821a59cb5bae4002739678c23d685219e828f924efa'},
                     '4533': {'description': '59cdc7b62d176f33cdd2d5cea2d4d740ed23a4e97e8721162d6982d4ab1e0387',
                              'primaryImage': '8547ab3e7553e93475dd9509e605e0554e14b187076e3033ac4714b2f423639b',
                              'images': 'aa15db4a728b06d66c58fa323bbe5f5cd6dad3a0f9789faa1ede44875b580334'},
                     '7619': {'description': '1af57c319a8d4b2407826ca0952a86bfbaa9663d4fbb8fef50b417a15664215a',
                              'primaryImage': 'd12fd0e2127dc4fc2539c68c0da406a32ff4dcd90b0f8f1ba20a0720a3845406',
                              'images': 'a7122580e6a788f1d171aa5ea7804b12d68c28cbc3d43817c08a841141fc96a6'},
                     '9843': {'description': '1c313769c489ad13b40200035d44fd0c333a4d768aa7b136ce6259bb6e7a873a',
                              'primaryImage': '4e78a58405434dbd17c3fdf25c9878ea7e76a1cc329aa8036e0bfcb408bba0a1',
                              'images': 'c35c25134283f8c0c3fca69cbc371fcf171b5e35b509f19041c6cffbddba3d2c'},
                     '9842': {'description': 'd49ac082ce36771ff498abfa7a571cf595ea75cea753255070db6a403e101b0f',
                              'primaryImage': '0311a4e1926a9a2d8fb562103421bee9f828d9a563915ba80af5d462c39d7825',
                              'images': 'ec8104c76a3f324fa4b6f49d52e23be0eef61fe9b579c6b00ba58980d2f65f5c'},
                     '10377': {'description': 'fec44b9f3a06937da0884d35c1b8809bad5bcce07bb0919c77571b260708f98d',
                               'primaryImage': '7578c5bbf6ff22cc85f0c8a7a647fba52701a9155994a4e8840baa9cf2065ec8',
                               'images': '2e20f51551c59bd8c2bb5e63c5959d27e21f7df1de0876de4b3f81e2a17fa50b'},
                     '4172': {'description': '99ef74925bb040cf38bf5e330a1754e84570ed0035951383e732d3ce4ae34914'},
                     '4539': {'description': '5581a2619280c28193085704916aed3925befb6fdeb91b441ceae264d41bfded',
                              'primaryImage': '140af709a244edafab53adcfef939b510b8792b2009d18f44b2f025cd11cf960',
                              'images': 'ef45efcef638a345ecac03dadb31d295e8cd7a8554e74a167bb6157a8cf8aabc'},
                     '4538': {'description': '5217a47726664563b9bd2d7ac073f581b9b63e7d6b3ee239099dd0340079eb95',
                              'primaryImage': 'a8f0bca0618e66f1697512dc88bcc3997c77aab91ae97fda87009037454fd202',
                              'images': '6c4931f747bf18457271cf74cb18542f9e21a663dce718ccbd49a8d198260365'},
                     '4601': {'description': 'c4c4bd1a190263337dd0a6d1293656431be14b6c1b8a41e7c621e2c1e7e2f662',
                              'primaryImage': '72ad785932d3c601d3acbeb5088520ae1e953230ea810e0b11faa33d79dcbf03',
                              'images': 'e4cb9206012a8cee3be753c3269d56bbd9a0311c6f03435d0759d896ef28851c'},
                     '14029': {'description': 'cc138fa78b4002362eddbda4f63eafe6b0298d6ebfea3e8def33532e2523c4d0',
                               'primaryImage': 'f7961f68efb4b3c20ef09d2be10ea2d3c65d437d1ecfb3d735ee6e3cd76e0133',
                               'images': 'd19a1847cd2dc1631950f61b9cdc3f94bc85e49d90f86e05429e6a8c1d1a306b'},
                     '12816': {'description': 'c740b0b8d7512266313dbea717623d55598e425f585369e7bdd214a9b92b7b40',
                               'primaryImage': '1c1cee7087c4d6773e1cee08f8ec673de6adf0d81f1ad6ffe0dcad9bdeb314e2',
                               'images': 'ef700edb881a258efb8a9cb2e6776e3257e7376e7cd41f322f899c2f6bf91661'},
                     '14013': {'description': 'c24882960380230bedffd27ff857f238abc75ff8b43ed57f6fd600f70634f8ef',
                               'primaryImage': 'ff10f3769abf5e70537473ed4a4e27f5c1b7b5664b133fd96ff09f613b2e3653',
                               'images': 'a60d328821b7abf766902dee7220443557ebb7c92197a814c4e35a8252adb210'}}}
LOCAL26_HASHES = {
    'v2/data/db-v1.php': SOURCE_HASHES['v2/data/db-v1.php'],
    LOCAL26_RUNNER: 'c6110cacb58d99589875f825d343a3ec8995e34c08746d648aa212bb595839e5',
    LOCAL26_MANIFEST: LOCAL26_IDENTITY['approved_manifest_sha256'],
}

LOCAL26_VALIDATOR = r'''
def l26_parent(prior,p):
    # Canonicalize parsed JSON; the downloaded pretty artifact hash is not a
    # private-file hash. The exact consumed parent's entire material is pinned.
    l11_need(isinstance(prior,dict) and hashlib.sha256(l11_json(prior)).hexdigest()==p['parent_canonical_sha256'],
        'local26_actual_parent_material')
    terminal=prior['local_official26']
    l11_need(prior['status']=='complete' and prior['mode']=='local-official26-apply-v1'
        and prior['operation_id']==p['parent_operation_id'] and prior['source_sha']==p['parent_source_sha']
        and prior['production_unchanged'] is True and prior['production_before']==prior['production_after'],
        'local26_parent_terminal')
    expected={'operation_id':p['parent_operation_id'],'source_sha':p['parent_source_sha'],
        'control_sha':p['parent_control_sha'],'manifest_sha256':p['approved_manifest_sha256'],
        'actions_sha256':p['actions_sha256'],'plan_sha256':p['plan_sha256'],
        'owner_receipt_sha256':p['owner_receipt_sha256'],'consumer_sha256':p['consumer_sha256'],
        'state':'committed_verified','requested_profiles':26,'profiles_updated':26,'requested_fields':76,'fields_updated':76,
        'database_writes':52,'profile_writes':26,'provenance_writes':26,'mapping_writes':0,'supplier_calls':0,
        'provider_http_calls':0,'legacy_writes':0,'schema_writes':0,'preserved_fields':0,
        'readback_verified':True,'consumer_readback_verified':True,'error_code':None}
    l11_need(all(type(terminal[k]) is type(v) and terminal[k]==v for k,v in expected.items()),'local26_parent_pins')
    l11_need(terminal['field_counts']=={'description':26,'primaryImage':25,'images':25}
        and terminal['protected_before']==terminal['protected_after']
        and terminal['protected_before']['data/db-v1.php']==LOCAL26_HASHES['v2/data/db-v1.php'],'local26_parent_readback')

def l26_observation(value,kind,p):
    l11_need(isinstance(value,dict),'local26_observation_shape')
    if kind=='own':
        l11_observation({k:v for k,v in value.items() if k!='approved_fields'},kind)
        expected=p['expected_fields'].get(str(value['own_id']))
        fields=value.get('approved_fields')
        l11_need(expected is not None and isinstance(fields,dict) and set(fields)==set(expected),'local26_approved_field_set')
        for name,want in expected.items():
            field=fields[name]
            l11_need(isinstance(field,dict) and set(field)=={'present','sha256','expected_sha256','matches'}
                and type(field['present']) is bool and type(field['matches']) is bool
                and field['expected_sha256']==want,'local26_approved_field_shape')
            l11_need(l11_hash(field['sha256']) if field['present'] else field['sha256'] is None,'local26_field_digest')
            l11_need(field['matches']==(field['present'] and field['sha256']==want),'local26_field_match')
    elif kind=='link':
        l11_need(set(value)>={'snapshot_source_count','snapshot_sources_integrity'},'local26_snapshot_sources')
        l11_observation({k:v for k,v in value.items() if k not in ('snapshot_source_count','snapshot_sources_integrity')},kind)
        count=value['snapshot_source_count'];valid=value['snapshot_sources_integrity']
        l11_need((count is None or type(count) is int and 0<=count<=64) and type(valid) is bool
            and (not valid or count is not None),'local26_snapshot_sources')
    else:l11_observation(value,kind)

def l26_row_facts(row,pair,p):
    own,tv,name,country,region=pair
    l11_need(row.get('own_id')==own and type(row['own_id']) is int and row.get('tv_id')==tv and type(row['tv_id']) is int,
        'local26_pair_identity')
    a=row['own'];b=row['bridge'];t=row['target'];linked=row['linked_target'];reasons=[]
    for value,kind,key,want in ((a,'own','own_id',own),(b,'link','own_id',own),(t,'target','tv_id',tv)):
        if value is not None:l26_observation(value,kind,p);l11_need(value[key]==want,'local26_observation_identity')
    if linked is not None:
        l26_observation(linked,'target',p)
        l11_need(b is not None and b['tv_id']!=tv and linked['tv_id']==b['tv_id'],'local26_linked_target')
    for key in ('expected_legacy_owners','target_link_owners','linked_target_link_owners'):
        ids=row[key]
        l11_need(isinstance(ids,list) and len(ids)<=128 and all(l11_int(v) for v in ids)
            and ids==sorted(set(ids)),'local26_owner_ids')
    sources=row['own_sources']
    l11_need(isinstance(sources,list) and len(sources)<=256,'local26_source_bound')
    for source in sources:l26_observation(source,'source',p);l11_need(source['own_id']==own,'local26_source_owner')
    l11_need(len({(s['namespace'],s['external_key']) for s in sources})==len(sources),'local26_duplicate_source')
    identity=None if a is None else [a['identity'][k] for k in ('name','country','region')]==[name,country,region]
    content='own_missing' if a is None else 'exact_approved_values' if all(v['matches'] for v in a['approved_fields'].values()) else 'approved_fields_missing_or_changed'
    if a is None:reasons.append('own_missing')
    else:
        if not a['active']:reasons.append('own_inactive')
        if not a['profile_integrity']:reasons.append('own_profile_integrity_failed')
        if not identity:reasons.append('canonical_identity_changed')
        if a['revision']<2:reasons.append('official26_revision_not_observed')
        if content!='exact_approved_values':reasons.append(content)
    if any(not s['source_integrity'] for s in sources):reasons.append('own_source_integrity_failed')
    legacy=[s for s in sources if s['namespace']=='legacy_catalog']
    l11_need((own in row['expected_legacy_owners'])==any(s['external_key']==str(tv) for s in legacy),'local26_legacy_owner_consistency')
    if not legacy:legacy_status='absent';reasons.append('legacy_source_absent')
    elif len(legacy)!=1:legacy_status='ambiguous';reasons.append('legacy_source_ambiguous')
    elif legacy[0]['external_key']!=str(tv):legacy_status='different_candidate';reasons.append('legacy_source_target_differs')
    elif not legacy[0]['source_integrity'] or legacy[0]['source_identity_id']!=tv or legacy[0]['acquired_via']!='saved_catalog':
        legacy_status='unverified';reasons.append('legacy_source_provenance_unconfirmed')
    elif row['expected_legacy_owners']!=[own]:legacy_status='conflicting_owners'
    else:legacy_status='observed_candidate_edge'
    if any(v!=own for v in row['expected_legacy_owners']):reasons.append('legacy_candidate_owned_by_other_own')
    if any(v!=own for v in row['target_link_owners']):reasons.append('candidate_linked_to_other_own')
    if any(v!=own for v in row['linked_target_link_owners']):reasons.append('actual_target_linked_to_other_own')
    if t is None:reasons.append('candidate_target_absent')
    else:
        if t['state'] in ('excluded','generic'):reasons.append('candidate_target_'+t['state'])
        if t['manual_fields']:reasons.append('candidate_manual_fields_present')
        if set(t['manual_fields'])&{'name','country','region','country.name','country.id','region.name','region.id'}:
            reasons.append('candidate_manual_identity_present')
        if not t['source_present']:reasons.append('candidate_native_source_absent')
        elif not t['source_integrity']:reasons.append('candidate_native_source_integrity_failed')
        if t['source_present'] and t['source_identity_id']!=tv:reasons.append('candidate_native_source_identity_unconfirmed')
        identities=[t[k] for k in ('discovery_identity','content_identity','manual_identity')]
        expected={'name':name,'country':country,'region':region}
        if any(i[k] is not None and i[k]!=v for i in identities for k,v in expected.items()):
            reasons.append('candidate_identity_differs_from_canonical')
        if any(not any(i[k]==v for i in identities) for k,v in expected.items()):reasons.append('candidate_identity_incomplete')
    status='absent' if b is None else 'candidate_target' if b['tv_id']==tv else 'different_target'
    relation='no_bridge'
    if b is None:
        reasons.append('bridge_absent')
        l11_need(linked is None and own not in row['target_link_owners'] and row['linked_target_link_owners']==[],
            'local26_absent_bridge_consistency')
    else:
        l11_need((own in row['target_link_owners'])==(b['tv_id']==tv) and own in row['linked_target_link_owners'],
            'local26_bridge_owner_consistency')
        if b['tv_id']!=tv:reasons.append('bridge_target_differs')
        else:l11_need(linked is None and row['linked_target_link_owners']==row['target_link_owners'],'local26_candidate_bridge_consistency')
        if not b['snapshot_integrity'] or not b['snapshot_profile_integrity'] or not b['snapshot_sources_integrity']:
            reasons.append('bridge_snapshot_integrity_failed');relation='invalid'
        elif b['snapshot_own_id']!=own:reasons.append('bridge_snapshot_own_differs');relation='identity_mismatch'
        elif a is None:relation='own_missing'
        elif b['snapshot_revision']==a['revision'] and b['snapshot_profile_sha256']==a['profile_sha256']:relation='same_profile'
        else:relation='historical_profile'
        if not b['snapshot_source_count']:reasons.append('bridge_source_snapshot_absent')
    actual=t if b is not None and b['tv_id']==tv else linked
    visibility='missing_link' if b is None else 'target_missing' if actual is None else 'target_excluded' if actual['state']=='excluded' else 'visible'
    return {'reasons':sorted(reasons),'own_identity_matches':identity,'approved_content_status':content,
        'legacy_candidate_status':legacy_status,'bridge_status':status,'snapshot_profile_relation':relation,
        'reverse_visibility':visibility,'reverse_details_available':actual is not None and visibility=='visible'
            and actual['state']=='ready' and actual['source_present']}

def l26_summary(rows):
    fields=[v for row in rows if row['own'] is not None for v in row['own']['approved_fields'].values()]
    return {'requested_profiles':26,'approved_fields_requested':76,'own_profiles_present':sum(r['own'] is not None for r in rows),
        'own_profiles_integrity_ok':sum(r['own'] is not None and r['own']['profile_integrity'] for r in rows),
        'canonical_identities_matching':sum(r['own_identity_matches'] is True for r in rows),
        'approved_fields_present':sum(v['present'] for v in fields),'approved_fields_matching':sum(v['matches'] for v in fields),
        'approved_profiles_all_fields_matching':sum(r['approved_content_status']=='exact_approved_values' for r in rows),
        'legacy_candidate_edges_observed':sum(r['legacy_candidate_status']=='observed_candidate_edge' for r in rows),
        'bridges_present':sum(r['bridge'] is not None for r in rows),
        'bridges_to_candidate':sum(r['bridge_status']=='candidate_target' for r in rows),
        'bridges_to_other_target':sum(r['bridge_status']=='different_target' for r in rows),
        'candidate_targets_present':sum(r['target'] is not None for r in rows),
        'candidate_targets_excluded':sum(r['target'] is not None and r['target']['state']=='excluded' for r in rows),
        'reverse_visible':sum(r['reverse_visibility']=='visible' for r in rows)}

def l26_project(raw,p):
    keys={'schema_version','state','captured_at_utc','database_snapshot','own_rows','source_rows','link_rows','target_rows',
        'supplier_calls','provider_http_calls','database_writes','mapping_writes','approved_manifest_sha256','actions_sha256'}
    l11_need(isinstance(raw,dict) and set(raw)==keys,'local26_snapshot_shape')
    l11_need(type(raw['schema_version']) is int and raw['schema_version']==1 and raw['state']=='local26_identity_snapshot'
        and raw['database_snapshot']=='repeatable-read-read-only','local26_snapshot_type');l11_time(raw['captured_at_utc'])
    for key in ('supplier_calls','provider_http_calls','database_writes','mapping_writes'):
        l11_need(type(raw[key]) is int and raw[key]==0,'local26_snapshot_writes')
    for key in ('approved_manifest_sha256','actions_sha256'):l11_need(raw[key]==p[key],'local26_approval_pin')
    own_ids={pair[0] for pair in p['pairs']};tv_ids={pair[1] for pair in p['pairs']}
    for name,kind,limit in (('own_rows','own',26),('source_rows','source',256),('link_rows','link',128),('target_rows','target',52)):
        l11_need(isinstance(raw[name],list) and len(raw[name])<=limit,'local26_snapshot_bound')
        for value in raw[name]:l26_observation(value,kind,p)
    own={r['own_id']:r for r in raw['own_rows']};links={r['own_id']:r for r in raw['link_rows']}
    targets={r['tv_id']:r for r in raw['target_rows']};sources=raw['source_rows']
    l11_need(len(own)==len(raw['own_rows']) and set(own)<=own_ids,'local26_own_scope')
    target_ids=tv_ids|{r['tv_id'] for r in links.values() if r['own_id'] in own_ids}
    l11_need(len(target_ids)<=52 and len(links)==len(raw['link_rows'])
        and all(r['own_id'] in own_ids or r['tv_id'] in target_ids for r in links.values()),'local26_link_scope')
    l11_need(len(targets)==len(raw['target_rows']) and set(targets)<=target_ids,'local26_target_scope')
    l11_need(len({(r['namespace'],r['external_key']) for r in sources})==len(sources),'local26_source_unique')
    l11_need(all(r['own_id'] in own_ids or (r['namespace']=='legacy_catalog' and r['external_key'] in {str(v) for v in tv_ids})
        for r in sources),'local26_source_scope')
    rows=[]
    for pair in p['pairs']:
        a,tv=pair[:2];b=links.get(a)
        row={'own_id':a,'tv_id':tv,'own':own.get(a),'bridge':b,'target':targets.get(tv),
            'linked_target':targets.get(b['tv_id']) if b is not None and b['tv_id']!=tv else None,
            'own_sources':sorted([r for r in sources if r['own_id']==a],key=lambda r:(r['namespace'],r['external_key'])),
            'expected_legacy_owners':sorted({r['own_id'] for r in sources if r['namespace']=='legacy_catalog' and r['external_key']==str(tv)}),
            'target_link_owners':sorted({r['own_id'] for r in links.values() if r['tv_id']==tv}),
            'linked_target_link_owners':sorted({r['own_id'] for r in links.values() if b is not None and r['tv_id']==b['tv_id']})}
        row.update(l26_row_facts(row,pair,p));rows.append(row)
    return {'state':'local26_identity_inspected_read_only','captured_at_utc':raw['captured_at_utc'],
        'database_snapshot':raw['database_snapshot'],'rows':rows,'summary':l26_summary(rows),'source_contract':p['source_contract'],
        'scope_sha256':hashlib.sha256(l11_json(p['pairs'])).hexdigest(),'approved_manifest_sha256':p['approved_manifest_sha256'],
        'actions_sha256':p['actions_sha256'],'supplier_calls':0,'provider_http_calls':0,'database_writes':0,'mapping_writes':0,
        'safe_to_write_now':False,'publication_admission':False,'accepted_native_bindings':0}

def l26_validate_data(data,p):
    keys={'state','captured_at_utc','database_snapshot','rows','summary','source_contract','scope_sha256','approved_manifest_sha256',
        'actions_sha256','supplier_calls','provider_http_calls','database_writes','mapping_writes','safe_to_write_now',
        'publication_admission','accepted_native_bindings','operation_id','source_sha','control_source_sha','cohort','target_operation_id',
        'parent_receipt_sha256','parent_canonical_sha256','parent_semantic_pins','current_raw_sha256','no_replay','new_database_reads',
        'protected_before','protected_after','protected_inputs_unchanged'}
    l11_need(isinstance(data,dict) and set(data)==keys,'local26_data_shape')
    l11_need(data['state']=='local26_identity_inspected_read_only' and data['database_snapshot']=='repeatable-read-read-only',
        'local26_data_type');l11_time(data['captured_at_utc'])
    for key in ('approved_manifest_sha256','actions_sha256','parent_canonical_sha256'):l11_need(data[key]==p[key],'local26_data_pin')
    l11_need(data['source_contract']==p['source_contract'] and data['scope_sha256']==hashlib.sha256(l11_json(p['pairs'])).hexdigest(),
        'local26_scope_digest')
    l11_need(data['parent_semantic_pins']=={k:p[k] for k in ('parent_operation_id','parent_source_sha','parent_control_sha',
        'plan_sha256','owner_receipt_sha256','consumer_sha256')},'local26_parent_semantics')
    for k in ('supplier_calls','provider_http_calls','database_writes','mapping_writes','accepted_native_bindings'):
        l11_need(type(data[k]) is int and data[k]==0,'local26_forbidden_mutation')
    l11_need(data['safe_to_write_now'] is False and data['publication_admission'] is False and data['no_replay'] is True
        and type(data['new_database_reads']) is int and data['new_database_reads']==1
        and l11_hash(data['current_raw_sha256']) and l11_hash(data['parent_receipt_sha256']),'local26_read_semantics')
    protected=data['protected_before']
    l11_need(isinstance(protected,dict) and set(protected)=={'parent/result.json','parent/reservation.json','config.php','data/db-v1.php'}
        and all(l11_hash(v) for v in protected.values()) and protected==data['protected_after']
        and protected['parent/result.json']==data['parent_receipt_sha256']
        and protected['data/db-v1.php']==LOCAL26_HASHES['v2/data/db-v1.php']
        and data['protected_inputs_unchanged'] is True,'local26_protected_inputs')
    rows=data['rows'];l11_need(isinstance(rows,list) and len(rows)==26,'local26_row_count')
    keys={'own_id','tv_id','own','bridge','target','linked_target','own_sources','expected_legacy_owners','target_link_owners',
        'linked_target_link_owners','reasons','own_identity_matches','approved_content_status','legacy_candidate_status',
        'bridge_status','snapshot_profile_relation','reverse_visibility','reverse_details_available'}
    targets={}
    for row,pair in zip(rows,p['pairs']):
        l11_need(isinstance(row,dict) and set(row)==keys,'local26_row_shape')
        l11_need(all(type(row[k]) is type(v) and row[k]==v for k,v in l26_row_facts(row,pair,p).items()),'local26_derived_fact_drift')
        for target in (row['target'],row['linked_target']):
            if target is not None:
                tid=target['tv_id'];l11_need(tid not in targets or targets[tid]==target,'local26_shared_target_drift');targets[tid]=target
    summary=l26_summary(rows)
    l11_need(isinstance(data['summary'],dict) and set(data['summary'])==set(summary)
        and all(type(data['summary'][k]) is int and data['summary'][k]==v for k,v in summary.items()),'local26_summary_drift')
'''
exec(LOCAL26_VALIDATOR)


def bundle_local26_source(source_root):
    root=Path(source_root).resolve();control=Path(__file__).resolve().parents[2];out=io.BytesIO()
    with tarfile.open(fileobj=out,mode='w:gz',format=tarfile.PAX_FORMAT) as archive:
        for relative,digest in LOCAL26_HASHES.items():
            path=(root if relative=='v2/data/db-v1.php' else control)/relative
            need(path.is_file() and not path.is_symlink() and path.resolve()==path and path.stat().st_nlink==1,'local26_source_path')
            raw=path.read_bytes()
            need(0<len(raw)<=1048576 and hashlib.sha256(raw).hexdigest()==digest,'local26_source_drift')
            info=tarfile.TarInfo(relative);info.size=len(raw);info.mode=0o600;info.mtime=0
            archive.addfile(info,io.BytesIO(raw))
        raw=l11_json({'schema_version':1,'files':LOCAL26_HASHES})
        info=tarfile.TarInfo('manifest.json');info.size=len(raw);info.mode=0o600;info.mtime=0
        archive.addfile(info,io.BytesIO(raw))
    return out.getvalue(),dict(LOCAL26_HASHES)


LOCAL26_HANDLER = r'''
def current_local26_identity(stage):
    p=LOCAL26_IDENTITY
    if (mode!='match-retained-current' or operation!=p['operation_id'] or source!=p['source_sha']
        or payload.get('cohort')!=p['cohort'] or payload.get('target_operation_id')!=p['parent_operation_id']):fail('local26_scope')
    target=private/p['parent_operation_id']
    paths={'parent/result.json':target/'result.json','parent/reservation.json':target/'reservation.json',
        'config.php':project/'config.php','data/db-v1.php':project/'data/db-v1.php'}
    def protected_hashes():
        hashes={}
        for name,path in paths.items():
            if not safe_file(path,1048576) or path.resolve()!=path or path.stat().st_nlink!=1:fail('local26_private_path')
            hashes[name]=hashlib.sha256(path.read_bytes()).hexdigest()
        return hashes
    protected=protected_hashes()
    prior=json.loads(paths['parent/result.json'].read_bytes(),object_pairs_hook=l11_unique);l26_parent(prior,p)
    if protected['data/db-v1.php']!=LOCAL26_HASHES['v2/data/db-v1.php']:fail('local26_installed_db_helper_drift')
    reserved=json.loads(paths['parent/reservation.json'].read_bytes(),object_pairs_hook=l11_unique)
    if (not isinstance(reserved,dict) or set(reserved)!={'operation_id','source_sha','mode','reserved_at'}
        or reserved.get('operation_id')!=p['parent_operation_id'] or reserved.get('source_sha')!=p['parent_source_sha']
        or reserved.get('mode')!='local-official26-apply-v1' or not l11_int(reserved.get('reserved_at'))):fail('local26_parent_reservation')
    def write_once(name,raw):
        fd=os.open(op/name,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
        with os.fdopen(fd,'wb') as handle:handle.write(raw);handle.flush();os.fsync(handle.fileno())
    write_once('local26-scope.json',l11_json({'pairs':[r[:2] for r in p['pairs']]}))
    env={k:os.environ[k] for k in ('HOME','PATH','LANG','LC_ALL') if k in os.environ}
    env.update(MATCH_RETAINED_DIR=str(op),MATCH_RETAINED_SOURCE_ROOT=str(stage))
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
    try:
        run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',
            '-d','memory_limit=256M','-d','disable_functions='+disabled,str(stage/LOCAL26_RUNNER)],
            cwd=project,env=env,capture_output=True,text=False,timeout=120)
    except subprocess.TimeoutExpired:fail('local26_child_timeout_no_replay')
    raw=run.stdout;write_once('local26-raw.json',raw[:1048576])
    if len(raw)>1048576:fail('local26_output_bound')
    if run.returncode or run.stderr:fail('local26_child_failed_no_replay')
    data=l26_project(json.loads(raw,object_pairs_hook=l11_unique),p);after=protected_hashes()
    if after!=protected:fail('local26_protected_drift')
    data.update(operation_id=operation,source_sha=source,control_source_sha=payload['match_retained_control_sha'],
        cohort=p['cohort'],target_operation_id=p['parent_operation_id'],parent_receipt_sha256=protected['parent/result.json'],
        parent_canonical_sha256=p['parent_canonical_sha256'],parent_semantic_pins={k:p[k] for k in (
            'parent_operation_id','parent_source_sha','parent_control_sha','plan_sha256','owner_receipt_sha256','consumer_sha256')},
        current_raw_sha256=hashlib.sha256(raw).hexdigest(),no_replay=True,new_database_reads=1,
        protected_before=protected,protected_after=after,protected_inputs_unchanged=True)
    l26_validate_data(data,p);evidence=l11_json(data);write_once('retained-evidence.json',evidence)
    return {'evidence_sha256':hashlib.sha256(evidence).hexdigest(),'data':data}
'''

LOCAL26_DISPATCH = r'''    if mode=='match-retained-current':
        result['supplier_calls']=result['database_writes']=result['mapping_writes']=0
        result['match_retained']=current_local26_identity(stage)
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True
        result['status']='complete'
'''


def completed_local26_identity(command,result,control):
    try:
        keys={'schema_version','operation_id','source_sha','mode','status','supplier_calls','database_writes','booking_calls',
            'lead_calls','production_before','production_after','production_unchanged','mapping_writes','match_retained'}
        if not isinstance(result,dict) or set(result)!=keys or type(result['schema_version']) is not int or result['schema_version']!=1:return False
        p=LOCAL26_IDENTITY
        if (command.get('mode')!='match-retained-current' or command.get('operation_id')!=p['operation_id']
            or command.get('source_sha')!=p['source_sha'] or command.get('cohort')!=p['cohort']
            or command.get('target_operation_id')!=p['parent_operation_id'] or not re.fullmatch(r'[a-f0-9]{40}',str(control))):return False
        if result['status']!='complete' or result['production_unchanged'] is not True:return False
        if any(result.get(k)!=command[k] for k in ('operation_id','source_sha','mode')):return False
        before=result['production_before']
        if (not isinstance(before,dict) or set(before)!={'index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'}
            or before!=result['production_after'] or any(v is not None and not l11_hash(v) for v in before.values())):return False
        if any(type(result[k]) is not int or result[k]!=0 for k in ('supplier_calls','database_writes','mapping_writes','booking_calls','lead_calls')):return False
        material=result['match_retained']
        if not isinstance(material,dict) or set(material)!={'evidence_sha256','data'}:return False
        data=material['data'];l26_validate_data(data,p)
        if any(data[k]!=command[k] for k in ('operation_id','source_sha','cohort','target_operation_id')) or data['control_source_sha']!=control:return False
        return material['evidence_sha256']==hashlib.sha256(l11_json(data)).hexdigest()
    except (ValueError,KeyError,TypeError,AttributeError,OverflowError):
        return False


def activate_local26_identity(core,command):
    fields=('source_sha','mode','operation_id','cohort','target_operation_id')
    expected=core.parse_command(core.PREFIX+' '.join(str(command.get(k,'')) for k in fields))
    need(command==expected,'local26_command_shape')
    control=os.environ.get('GITHUB_SHA','');need(re.fullmatch(r'[a-f0-9]{40}',control) is not None,'local26_control_sha')
    command['match_retained_control_sha']=control;remote=core.REMOTE
    anchors={'definition':'def run_match942(stage, mode, offset, limit):\n','dispatch':"    if mode=='match-tv942-write':\n",
        'collector':"    if mode not in ('reconcile',",'manifest':"    if not isinstance(files,dict) or len(files)<20: fail('manifest')",
        'members':'        members=package.getmembers()\n','reservation':'    op.mkdir(mode=0o700)\n',
        'receipt':"if 'op' in globals() and op.exists():"}
    for key,anchor in anchors.items():need(remote.count(anchor)==(2 if key=='collector' else 1),'local26_registration_drift_'+key)
    handler='\nLOCAL26_IDENTITY='+repr(LOCAL26_IDENTITY)+'\nLOCAL26_HASHES='+repr(LOCAL26_HASHES)+'\nLOCAL26_RUNNER='+repr(LOCAL26_RUNNER)+'\n'
    handler+=LOCAL11_VALIDATOR+LOCAL26_VALIDATOR+LOCAL26_HANDLER
    remote=remote.replace(anchors['definition'],handler+'\n'+anchors['definition'],1)
    remote=remote.replace(anchors['dispatch'],LOCAL26_DISPATCH+anchors['dispatch'],1)
    remote=remote.replace(anchors['collector'],"    if mode not in ('match-retained-current','reconcile',")
    remote=remote.replace(anchors['manifest'],"    if not isinstance(files,dict) or files!="+repr(LOCAL26_HASHES)+": fail('local26_manifest')",1)
    remote=remote.replace(anchors['members'],anchors['members']+"        if len(members)!=4 or {m.name for m in members}!={*LOCAL26_HASHES,'manifest.json'}: fail('local26_archive_members')\n",1)
    remote=remote.replace(anchors['reservation'],anchors['reservation']+'    local26_reserved=True\n',1)
    remote=remote.replace(anchors['receipt'],"if globals().get('local26_reserved') is True and op.exists():",1)
    ast.parse(remote);core.REMOTE=remote;core.bundle_source=bundle_local26_source
    original=core.completed_result
    core.completed_result=lambda cmd,res:completed_local26_identity(cmd,res,control) if cmd.get('cohort')==LOCAL26_IDENTITY['cohort'] else original(cmd,res)


# Finite LOCAL19 owner-approved apply. The immutable data proposal and actual
# approval receipt bind the sole operation; all drift and no-replay guards remain.
LOCAL19_RUNNER = 'scripts/catalog/anytour_local19_bridge_apply_v1.php'
LOCAL19_PROPOSAL = 'scripts/catalog/anytour-local19-bridge-proposal-20261123.json'
LOCAL19_APPLY = {'mode': 'local19-bridge-apply-v1',
 'cohort': 'local19-bridge-manual57-20261123',
 'operation_id': 'int-andromeda-local19-bridge-apply-20261123-v1',
 'source_sha': '3207266e17ed4e8244aaf28d05b962747fd4cd3c',
 'execution_admitted': True,
 'approval_ref': 'https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7088365340',
 'approved_proposal_sha256': '02d3df3ecd94d775e58fd3e506cf81032b64179fa3b4db1ff43de9e515ec2192',
 'proposal_sha256': '02d3df3ecd94d775e58fd3e506cf81032b64179fa3b4db1ff43de9e515ec2192',
 'parent_operation_id': 'int-andromeda-match-retained-current-20261123-v1',
 'parent_source_sha': '3207266e17ed4e8244aaf28d05b962747fd4cd3c',
 'parent_control_sha': 'cba860aaeefab5b6304da1fe8cf9d3b0896f7338',
 'parent_canonical_sha256': '7cc56caff2855564754fcb0104837c95611a259ea598aa1f28f3c9baa83a6db6',
 'parent_evidence_sha256': '5204754ad39ad2aab13e4a3df28c0663547b531a984c6e1b1454a62033e4426f',
 'parent_raw_sha256': '9157a50f472e33ff5a1902fd941d14b18776b35e1558df921cca32d1fcac6925',
 'pairs': [[4511, 2490, 'd6f4adf9da5ecf5bb9f5fa9e667e1ea70d7e1088386dc30405149e68a1fb7a5a'],
           [10334, 22940, '91caa4f92bc4b35bb71f64cdbbc28f8c8110a9ab29b7940efa31d3fcc464ad66'],
           [8751, 7831, 'a75799ae8d7a24775cf018162f6a32d5365ae544081ca5af3cd0a5109855d5d6'],
           [7599, 2458, '796ebf5b920ebf2b5705968e9b69f908e677f69c770a40b5e121f08736ed8e0d'],
           [14932, 113544, 'd628c080b916517d9c4537e1301368e6bd8e9dff56affc4ca337c850655c2580'],
           [12248, 52337, '7cdd7e9235712506832d5483b91470f3d096dfc08ed8d6e649be9b832cdc9586'],
           [4609, 2948, '7c4c45d4c6acb4604181fcb4055ba627ad5a77d8e0d0070210d786059c846b3c'],
           [12520, 57536, '0cd7378495da9ada8cd438b59bdf71e2f9df1f52254ac219dd509e09c20a6084'],
           [12839, 61241, '1eb16a6e5fb5b9a490e778baf8f7a8ad0d69785867bcdbc06cf9d4c669b8fc5d'],
           [13686, 73477, 'd5f54e4f44513e560d15665631837a49c546d8f850758f57cd1355d4f2d55824'],
           [13066, 64648, 'ff67f53c68f7778b960c2c79c519a9df18f5ae1c643c5b5be66a6975b5bb9584'],
           [4533, 2588, '989035326adf24ec682aba83480be4e25a13bb323f562905596d0a1d883f1a23'],
           [7619, 2611, '973e560dbad185aa809ff041c0c559f9da221df65426959062ab3e5606f19352'],
           [9843, 17424, '0a1cc925e268e33c54c6c0d83a5b7f52359192cbaaf79020b74b2831d861748b'],
           [9842, 17423, 'ee08b3827ba993cece3fcf06ed9050f26f6800f2d7e41f324f330ac791513331'],
           [10377, 23718, '3261f6486430559d0d9ee19419b00af46f6b58ecb03b003d909bb1c15f125ad4'],
           [4539, 2612, 'f87104fa4c3b98b8d71fffccebc59d9b0c7f4df17c18d9dec3beba9bc3a71a62'],
           [4538, 2610, '8bcabe6ef37c8ed51ed494fc30f829397926271637d43ca5ba790a31f84af144'],
           [4601, 2931, '6673483498f16799e2117ec4afdf9531897b320a2097f80e67b2af81a4cf9abb']],
 'excluded_pairs': [[4679, 4283],
                    [14397, 97001],
                    [13766, 74509],
                    [14029, 78669],
                    [12816, 60788],
                    [14013, 78456]],
 'unchanged_existing_pairs': [[4172, 80008]]}
LOCAL19_HASHES = {
    'v2/data/db-v1.php': SOURCE_HASHES['v2/data/db-v1.php'],
    LOCAL19_RUNNER: '37af31b380e96e9b2afd799559fa665952e446f80c5a930792233d0cb3730f24',
    LOCAL19_PROPOSAL: LOCAL19_APPLY['proposal_sha256'],
}

LOCAL19_VALIDATOR = r'''
def l19_admission(profile):
    # Admission requires both the exact proposal and its real owner receipt.
    # Missing, partial or previously consumed approval remains closed.
    ref=profile.get('approval_ref')
    l11_need(profile.get('execution_admitted') is True
        and profile.get('approved_proposal_sha256')==profile['proposal_sha256']
        and isinstance(ref,str) and re.fullmatch(
            r'https://github\.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-[1-9][0-9]{0,19}',ref)
        and ref not in (
            'https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7086996252',
            'https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7077944229',
            'https://github.com/pyatkoff/poisk-turov-test/issues/4709#issuecomment-7088077135'),
        'local19_owner_approval_required')

def l19_parent(prior):
    p=LOCAL19_APPLY
    command={'source_sha':p['parent_source_sha'],'mode':'match-retained-current',
        'operation_id':p['parent_operation_id'],'cohort':LOCAL26_IDENTITY['cohort'],
        'target_operation_id':LOCAL26_IDENTITY['parent_operation_id']}
    l11_need(completed_local26_identity(command,prior,p['parent_control_sha']),'local19_parent_terminal')
    material=prior['match_retained']
    l11_need(hashlib.sha256(l11_json(prior)).hexdigest()==p['parent_canonical_sha256']
        and material['evidence_sha256']==p['parent_evidence_sha256']
        and material['data']['current_raw_sha256']==p['parent_raw_sha256'],'local19_parent_evidence')

def l19_terminal(value):
    keys={'schema_version','operation_id','state','completed_at_utc','proposal_sha256','no_replay',
        'supplier_calls','provider_http_calls','database_writes','mapping_writes','native_updates','manual_field_writes',
        'canonical_profile_writes','source_writes','new_target_inserts','accepted_native_bindings',
        'commit_acknowledged','readback_verified','before','after','checks','pairs','error'}
    p=LOCAL19_APPLY
    l11_need(isinstance(value,dict) and set(value)==keys,'local19_terminal_shape')
    l11_need(value['schema_version']=='anytour_local19_bridge_apply_v1'
        and value['operation_id']==p['operation_id'] and value['proposal_sha256']==p['proposal_sha256']
        and value['no_replay'] is True,'local19_terminal_identity')
    l11_time(value['completed_at_utc'])
    for key in ('supplier_calls','provider_http_calls','canonical_profile_writes','source_writes','new_target_inserts','accepted_native_bindings'):
        l11_need(type(value[key]) is int and value[key]==0,'local19_forbidden_writes')
    state=value['state'];committed=state in ('committed_verified','post_commit_readback_unknown')
    unknown=state in ('commit_unknown','rollback_unknown')
    l11_need(committed or unknown or state in ('rejected_before_write','rolled_back'),'local19_terminal_state')
    for key,count in zip(('database_writes','mapping_writes','native_updates','manual_field_writes'),(38,19,19,57)):
        l11_need(value[key] is None if unknown else type(value[key]) is int and value[key]==(count if committed else 0),
            'local19_write_counts')
    l11_need(type(value['commit_acknowledged']) is bool and value['commit_acknowledged']==committed
        and type(value['readback_verified']) is bool and value['readback_verified']==(state=='committed_verified'),
        'local19_commit_semantics')
    for name in ('before','after'):
        hashes=value[name]
        l11_need(hashes is None or isinstance(hashes,dict) and set(hashes)=={
            'canonical_rows_sha256','target_preserved_rows_sha256','excluded_rows_sha256'}
            and all(l11_hash(v) for v in hashes.values()),'local19_row_digests')
    checks=value['checks']
    l11_need(isinstance(checks,dict) and set(checks)=={'fresh_parent_match','canonical_unchanged',
        'target_preserved','excluded_unchanged','direct_tv_sql','own_to_tv_sql','snapshots_verified'},'local19_checks')
    for name,v in checks.items():
        l11_need(type(v) is bool or name in ('canonical_unchanged','target_preserved','excluded_unchanged') and v is None,
            'local19_checks')
    pairs=value['pairs']
    l11_need(isinstance(pairs,list) and len(pairs) in (0,19),'local19_pair_count')
    for row,expected in zip(pairs,p['pairs']):
        l11_need(isinstance(row,dict) and set(row)=={'own_id','tv_id','revision_from','revision_to','manual_fields',
            'manual_sha256','snapshot_sha256','direct_tv_sql','own_to_tv_sql'},'local19_pair_shape')
        for name,want in zip(('own_id','tv_id','revision_from','revision_to'),(*expected[:2],1,2)):
            l11_need(type(row[name]) is int and row[name]==want,'local19_pair_identity')
        l11_need(row['manual_fields']==['description','primaryImage','images'] and row['manual_sha256']==expected[2]
            and l11_hash(row['snapshot_sha256']) and type(row['direct_tv_sql']) is bool
            and type(row['own_to_tv_sql']) is bool,'local19_pair_evidence')
    if state=='committed_verified':
        l11_need(value['before'] is not None and value['before']==value['after'] and len(pairs)==19
            and all(v is True for v in checks.values()) and all(r['direct_tv_sql'] and r['own_to_tv_sql'] for r in pairs)
            and value['error'] is None,'local19_committed_readback')
    else:
        error=value['error']
        l11_need(isinstance(error,dict) and set(error)=={'code','message_sha256'}
            and isinstance(error['code'],str) and re.fullmatch(r'[a-z0-9_]{1,96}',error['code'])
            and l11_hash(error['message_sha256']),'local19_failure_evidence')
    return value

def l19_validate_data(data,command,control):
    keys={'operation_id','source_sha','control_source_sha','cohort','target_operation_id','approval_ref','proposal_sha256',
        'parent_receipt_sha256','parent_evidence_sha256','raw_sha256','no_replay','child_exit_code','child_stderr_sha256',
        'protected_before','protected_after','protected_inputs_unchanged','terminal'}
    l11_need(isinstance(data,dict) and set(data)==keys,'local19_data_shape')
    p=LOCAL19_APPLY
    l19_admission(p)
    for key,want in {'operation_id':p['operation_id'],'source_sha':p['source_sha'],'cohort':p['cohort'],
        'target_operation_id':p['parent_operation_id'],'approval_ref':p['approval_ref'],'proposal_sha256':p['proposal_sha256'],
        'parent_evidence_sha256':p['parent_evidence_sha256'],'control_source_sha':control}.items():
        l11_need(data[key]==want,'local19_data_binding')
    l11_need(all(command.get(k)==p[k] for k in ('operation_id','source_sha','cohort','mode'))
        and command.get('target_operation_id')==p['parent_operation_id']
        and re.fullmatch(r'[a-f0-9]{40}',str(control)) is not None,'local19_command_binding')
    l11_need(data['no_replay'] is True and l11_hash(data['parent_receipt_sha256']) and l11_hash(data['raw_sha256'])
        and (data['child_stderr_sha256'] is None or l11_hash(data['child_stderr_sha256'])),'local19_data_evidence')
    terminal=l19_terminal(data['terminal'])
    l11_need(type(data['child_exit_code']) is int and data['child_exit_code']==(0 if terminal['state']=='committed_verified' else 1),
        'local19_child_exit')
    before=data['protected_before']
    l11_need(isinstance(before,dict) and set(before)=={'parent/result.json','parent/reservation.json','config.php','data/db-v1.php'}
        and all(l11_hash(v) for v in before.values()) and data['protected_after']==before
        and before['parent/result.json']==data['parent_receipt_sha256']
        and before['data/db-v1.php']==LOCAL19_HASHES['v2/data/db-v1.php']
        and data['protected_inputs_unchanged'] is True,'local19_protected_inputs')
'''
exec(LOCAL19_VALIDATOR)

def bundle_local19_source(source_root):
    l19_admission(LOCAL19_APPLY)
    root=Path(source_root).resolve();control=Path(__file__).resolve().parents[2];out=io.BytesIO()
    with tarfile.open(fileobj=out,mode='w:gz',format=tarfile.PAX_FORMAT) as archive:
        for relative,digest in LOCAL19_HASHES.items():
            path=(root if relative=='v2/data/db-v1.php' else control)/relative
            need(path.is_file() and not path.is_symlink() and path.resolve()==path and path.stat().st_nlink==1,'local19_source_path')
            raw=path.read_bytes()
            need(0<len(raw)<=1048576 and hashlib.sha256(raw).hexdigest()==digest,'local19_source_drift')
            info=tarfile.TarInfo(relative);info.size=len(raw);info.mode=0o600;info.mtime=0
            archive.addfile(info,io.BytesIO(raw))
        raw=l11_json({'schema_version':1,'files':LOCAL19_HASHES})
        info=tarfile.TarInfo('manifest.json');info.size=len(raw);info.mode=0o600;info.mtime=0
        archive.addfile(info,io.BytesIO(raw))
    return out.getvalue(),dict(LOCAL19_HASHES)

LOCAL19_HANDLER = r'''
def apply_local19_bridge(stage):
    p=LOCAL19_APPLY
    l19_admission(p)
    if (mode!=p['mode'] or operation!=p['operation_id'] or source!=p['source_sha']
        or payload.get('cohort')!=p['cohort'] or payload.get('target_operation_id')!=p['parent_operation_id']):
        fail('local19_fixed_write_scope')
    target=private/p['parent_operation_id']
    paths={'parent/result.json':target/'result.json','parent/reservation.json':target/'reservation.json',
        'config.php':project/'config.php','data/db-v1.php':project/'data/db-v1.php'}
    def guarded_hashes():
        out={}
        for name,path in paths.items():
            if not safe_file(path,1048576) or path.resolve()!=path or path.stat().st_nlink!=1:fail('local19_protected_path')
            out[name]=hashlib.sha256(path.read_bytes()).hexdigest()
        return out
    protected=guarded_hashes()
    if protected['data/db-v1.php']!=LOCAL19_HASHES['v2/data/db-v1.php']:fail('local19_installed_db_helper_drift')
    prior=json.loads(paths['parent/result.json'].read_bytes(),object_pairs_hook=l11_unique);l19_parent(prior)
    reserved=json.loads(paths['parent/reservation.json'].read_bytes(),object_pairs_hook=l11_unique)
    if (not isinstance(reserved,dict) or set(reserved)!={'operation_id','source_sha','mode','reserved_at'}
        or reserved.get('operation_id')!=p['parent_operation_id'] or reserved.get('source_sha')!=p['parent_source_sha']
        or reserved.get('mode')!='match-retained-current' or not l11_int(reserved.get('reserved_at'))):
        fail('local19_parent_reservation')
    def write_once(name,raw):
        fd=os.open(op/name,os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
        with os.fdopen(fd,'wb') as handle:handle.write(raw);handle.flush();os.fsync(handle.fileno())
    write_once('local19-child-reservation.json',l11_json({'operation_id':operation,'source_sha':source,
        'control_source_sha':payload['match_retained_control_sha'],'approval_ref':p['approval_ref'],
        'proposal_sha256':p['proposal_sha256'],'parent_evidence_sha256':p['parent_evidence_sha256'],'no_replay':True}))
    env={k:os.environ[k] for k in ('HOME','PATH','LANG','LC_ALL') if k in os.environ}
    env.update(MATCH_RETAINED_DIR=str(op),MATCH_RETAINED_SOURCE_ROOT=str(stage),MATCH_LOCAL19_EXECUTION_ADMITTED='1')
    disabled='curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,socket_create,socket_connect,exec,system,shell_exec,passthru,proc_open,popen'
    result['database_writes']=result['mapping_writes']=None
    try:
        run=subprocess.run(['php','-d','display_errors=0','-d','log_errors=0','-d','allow_url_fopen=0',
            '-d','memory_limit=256M','-d','disable_functions='+disabled,str(stage/LOCAL19_RUNNER)],
            cwd=project,env=env,capture_output=True,text=False,timeout=120)
    except subprocess.TimeoutExpired as exc:
        raw=exc.stdout or b''
        if isinstance(raw,str):raw=raw.encode()
        write_once('local19-raw.json',raw[:1048576])
        fail('local19_child_timeout_no_replay')
    raw=run.stdout
    write_once('local19-raw.json',raw[:1048576])
    if len(raw)>1048576:fail('local19_output_bound_no_replay')
    terminal=l19_terminal(json.loads(raw,object_pairs_hook=l11_unique))
    result['database_writes']=terminal['database_writes'];result['mapping_writes']=terminal['mapping_writes']
    after=guarded_hashes()
    data={'operation_id':operation,'source_sha':source,'control_source_sha':payload['match_retained_control_sha'],
        'cohort':p['cohort'],'target_operation_id':p['parent_operation_id'],'approval_ref':p['approval_ref'],
        'proposal_sha256':p['proposal_sha256'],'parent_receipt_sha256':protected['parent/result.json'],
        'parent_evidence_sha256':p['parent_evidence_sha256'],'raw_sha256':hashlib.sha256(raw).hexdigest(),
        'no_replay':True,'child_exit_code':run.returncode,
        'child_stderr_sha256':hashlib.sha256(run.stderr).hexdigest() if run.stderr else None,
        'protected_before':protected,'protected_after':after,'protected_inputs_unchanged':protected==after,'terminal':terminal}
    evidence=l11_json(data);write_once('retained-evidence.json',evidence)
    material={'evidence_sha256':hashlib.sha256(evidence).hexdigest(),'data':data}
    result['match_retained']=material
    l19_validate_data(data,payload,payload['match_retained_control_sha'])
    if run.stderr:fail('local19_child_stderr_no_replay')
    return material
'''

LOCAL19_DISPATCH = r'''    if mode==LOCAL19_APPLY['mode']:
        result['supplier_calls']=result['database_writes']=result['mapping_writes']=0
        result['match_retained']=apply_local19_bridge(stage)
        terminal=result['match_retained']['data']['terminal']
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True
        result['status']=('complete' if terminal['state']=='committed_verified' else
            'rolled_back_no_replay' if terminal['state']=='rolled_back' else
            'rejected_no_replay' if terminal['state']=='rejected_before_write' else 'unknown_no_replay')
'''

def completed_local19_apply(command,result,control):
    try:
        keys={'schema_version','operation_id','source_sha','mode','status','supplier_calls','database_writes',
            'booking_calls','lead_calls','production_before','production_after','production_unchanged','mapping_writes','match_retained'}
        if not isinstance(result,dict) or set(result)!=keys or type(result['schema_version']) is not int or result['schema_version']!=1:return False
        if result['status']!='complete' or result['production_unchanged'] is not True:return False
        if any(result.get(k)!=command.get(k) for k in ('operation_id','source_sha','mode')):return False
        before=result['production_before']
        if (not isinstance(before,dict) or set(before)!={'index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'}
            or before!=result['production_after'] or any(v is not None and not l11_hash(v) for v in before.values())):return False
        material=result['match_retained']
        if not isinstance(material,dict) or set(material)!={'evidence_sha256','data'}:return False
        data=material['data'];l19_validate_data(data,command,control)
        if data['terminal']['state']!='committed_verified' or data['child_stderr_sha256'] is not None:return False
        for name,want in {'supplier_calls':0,'database_writes':38,'mapping_writes':19,'booking_calls':0,'lead_calls':0}.items():
            if type(result[name]) is not int or result[name]!=want:return False
        return material['evidence_sha256']==hashlib.sha256(l11_json(data)).hexdigest()
    except (ValueError,KeyError,TypeError,AttributeError,OverflowError):
        return False

def activate_local19_apply(core,command):
    l19_admission(LOCAL19_APPLY)
    fields=('source_sha','mode','operation_id','cohort','target_operation_id')
    expected=core.parse_command(core.PREFIX+' '.join(str(command.get(k,'')) for k in fields))
    need(command==expected,'local19_command_shape')
    control=os.environ.get('GITHUB_SHA','')
    need(re.fullmatch(r'[a-f0-9]{40}',control) is not None,'local19_control_sha')
    command['match_retained_control_sha']=control
    remote=core.REMOTE
    anchors={'definition':'def run_match942(stage, mode, offset, limit):\n',
        'dispatch':"    if mode=='match-tv942-write':\n",'collector':"    if mode not in ('reconcile',",
        'manifest':"    if not isinstance(files,dict) or len(files)<20: fail('manifest')",
        'members':'        members=package.getmembers()\n','reservation':'    op.mkdir(mode=0o700)\n',
        'receipt':"if 'op' in globals() and op.exists():"}
    for key,anchor in anchors.items():
        need(remote.count(anchor)==(2 if key=='collector' else 1),'local19_registration_drift_'+key)
    handler='\nLOCAL11_IDENTITY='+repr(LOCAL11_IDENTITY)+'\nLOCAL11_HASHES='+repr(LOCAL11_HASHES)+'\n'+LOCAL11_VALIDATOR
    handler+='\nLOCAL26_IDENTITY='+repr(LOCAL26_IDENTITY)+'\nLOCAL26_HASHES='+repr(LOCAL26_HASHES)+'\n'+LOCAL26_VALIDATOR
    handler+='\n'+inspect.getsource(completed_local26_identity)
    handler+='\nLOCAL19_APPLY='+repr(LOCAL19_APPLY)+'\nLOCAL19_HASHES='+repr(LOCAL19_HASHES)+'\nLOCAL19_RUNNER='+repr(LOCAL19_RUNNER)+'\n'
    handler+=LOCAL19_VALIDATOR+LOCAL19_HANDLER
    remote=remote.replace(anchors['definition'],handler+'\n'+anchors['definition'],1)
    remote=remote.replace(anchors['dispatch'],LOCAL19_DISPATCH+anchors['dispatch'],1)
    remote=remote.replace(anchors['collector'],"    if mode not in ('local19-bridge-apply-v1','reconcile',")
    remote=remote.replace(anchors['manifest'],"    if not isinstance(files,dict) or files!="+repr(LOCAL19_HASHES)+": fail('local19_manifest')",1)
    remote=remote.replace(anchors['members'],anchors['members']+"        if len(members)!=4 or {m.name for m in members}!={*LOCAL19_HASHES,'manifest.json'}: fail('local19_archive_members')\n",1)
    remote=remote.replace(anchors['reservation'],anchors['reservation']+'    local19_reserved=True\n',1)
    remote=remote.replace(anchors['receipt'],"if globals().get('local19_reserved') is True and op.exists():",1)
    ast.parse(remote)
    core.REMOTE=remote;core.bundle_source=bundle_local19_source
    original=core.completed_result
    core.completed_result=lambda cmd,res:completed_local19_apply(cmd,res,control) if cmd.get('mode')==LOCAL19_APPLY_MODE else original(cmd,res)


# One saved capture, seven fixed IDs. This profile neither registers targets nor
# revisits the historical native-binding projection or its supplier contexts.
LOCAL7_REGISTRATION = {
    'mode': 'match-retained-readback',
    'operation_id': 'int-andromeda-match-retained-readback-20261123-v1',
    'cohort': 'local7-registration-evidence-20261123',
    'source_sha': '3207266e17ed4e8244aaf28d05b962747fd4cd3c',
    'parent_operation_id': 'int-andromeda-match-retained-20261020-v3',
    'parent_receipt_sha256': 'b610629916e80d706edb493da1fc02eb0bf38a00de64243a1973bb80155485d6',
    'capture_raw_sha256': '00c992ec930b0f0c8c9a8167bea93a1efebd8d34e1c19f1a01bf16e3ef77d834',
    'capture_raw_bytes': 62108585,
    'pairs': [[8689,7458],[4679,4283],[14397,97001],[13766,74509],
              [14029,78669],[12816,60788],[14013,78456]],
    'max_observations_per_tv': 128,
    'max_line_bytes': 4194304,
}

LOCAL7_VALIDATOR = r'''
import datetime as l7_datetime
import io as l7_io

L7_COLUMNS = ['id','hotel_id','search_id','source','first_seen_at','last_seen_at']
L7_SOURCES = ('user_search','scheduled_monitor','hot_tours')
L7_REASONS = ('search_id_not_positive','source_not_allowed','first_seen_at_invalid','last_seen_at_invalid')
L7_ZERO = ('supplier_calls','provider_http_calls','database_writes','mapping_writes',
           'booking_calls','lead_calls','new_database_reads','new_target_inserts','accepted_native_bindings')
L7_FALSE = ('native_authority','target_creation_authority','ai_authority')
L7_COMMAND = ('source_sha','mode','operation_id','cohort','target_operation_id')
L7_PROJECTION = {'captured_at_utc','catalog_id_namespace','database_snapshot','scope_line_sha256',
                 'required_observation_columns','rows','rows_sha256'}

def l7_need(value, reason):
    if not value: raise ValueError('local7_'+reason)

def l7_json(value):
    return json.dumps(value,sort_keys=True,separators=(',',':'),allow_nan=False).encode()

def l7_unique(pairs):
    value={}
    for key,item in pairs:
        l7_need(key not in value,'duplicate_json_key');value[key]=item
    return value

def l7_parse(raw):
    def invalid(value): raise ValueError('local7_nonfinite_json')
    try: return json.loads(raw,object_pairs_hook=l7_unique,parse_constant=invalid)
    except (UnicodeError,ValueError) as error:
        if str(error).startswith('local7_'): raise
        raise ValueError('local7_invalid_json') from None

def l7_hash(value):
    return isinstance(value,str) and re.fullmatch(r'[a-f0-9]{64}',value) is not None

def l7_sha(value):
    return isinstance(value,str) and re.fullmatch(r'[a-f0-9]{40}',value) is not None

def l7_positive(value):
    if isinstance(value,str) and re.fullmatch(r'[1-9][0-9]{0,19}',value): value=int(value)
    return value if type(value) is int and 0<value<=18446744073709551615 else None

def l7_time(value):
    if not isinstance(value,str) or re.fullmatch(r'[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}',value) is None: return False
    try: l7_datetime.datetime.strptime(value,'%Y-%m-%d %H:%M:%S');return True
    except ValueError: return False

def l7_capture_time(value):
    if not isinstance(value,str) or re.fullmatch(r'[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:Z|\+00:00)',value) is None:return False
    return l7_time(value[:10]+' '+value[11:19])

def l7_command(command,control,p):
    wanted={'source_sha':p['source_sha'],'mode':p['mode'],'operation_id':p['operation_id'],
            'cohort':p['cohort'],'target_operation_id':p['parent_operation_id']}
    l7_need(isinstance(command,dict) and set(command) in (set(wanted),set(wanted)|{'match_retained_control_sha'})
        and all(command.get(k)==v for k,v in wanted.items()) and l7_sha(control)
        and command.get('match_retained_control_sha',control)==control,'command_binding')

def l7_validate_projection(value,p):
    l7_need(isinstance(value,dict) and set(value)==L7_PROJECTION,'projection_shape')
    l7_need(l7_capture_time(value['captured_at_utc']) and value['catalog_id_namespace']=='tourvisor_hotel'
        and value['database_snapshot']=='repeatable-read-read-only' and l7_hash(value['scope_line_sha256'])
        and value['required_observation_columns']==L7_COLUMNS,'capture_scope')
    rows=value['rows'];l7_need(isinstance(rows,list) and len(rows)==7,'row_count')
    seen=set()
    for row,pair in zip(rows,p['pairs']):
        l7_need(isinstance(row,dict) and set(row)=={'own_id','tv_id','matched_observations','qualifying_observations',
            'state','scope','witnesses','rejection_reasons'},'row_shape')
        l7_need(type(row['own_id']) is int and type(row['tv_id']) is int
            and [row['own_id'],row['tv_id']]==list(pair) and row['scope']=='IN_THIS_CAPTURE','row_identity')
        matched=row['matched_observations'];qualifying=row['qualifying_observations']
        l7_need(type(matched) is int and type(qualifying) is int
            and 0<=qualifying<=matched<=p['max_observations_per_tv'],'row_counts')
        state='no_observation' if matched==0 else 'no_qualifying_observation' if qualifying==0 else 'witness_present'
        l7_need(row['state']==state,'row_state')
        reasons=row['rejection_reasons'];rejected=matched-qualifying
        l7_need(isinstance(reasons,dict) and set(reasons)<=set(L7_REASONS)
            and all(type(n) is int and 0<n<=rejected for n in reasons.values())
            and rejected<=sum(reasons.values())<=len(L7_REASONS)*rejected,'rejection_counts')
        witnesses=row['witnesses'];l7_need(isinstance(witnesses,list) and len(witnesses)==qualifying,'witness_count')
        for witness in witnesses:
            l7_need(isinstance(witness,dict) and set(witness)=={'observation_id','hotel_id','search_id',
                'source','first_seen_at','last_seen_at','line_sha256'},'witness_shape')
            l7_need(all(type(witness[k]) is int and l7_positive(witness[k]) is not None
                    for k in ('observation_id','hotel_id','search_id'))
                and witness['hotel_id']==pair[1] and witness['observation_id'] not in seen,'witness_identity')
            seen.add(witness['observation_id'])
            l7_need(witness['source'] in L7_SOURCES and l7_time(witness['first_seen_at'])
                and l7_time(witness['last_seen_at']) and l7_hash(witness['line_sha256']),'witness_predicates')
    l7_need(value['rows_sha256']==hashlib.sha256(l7_json(rows)).hexdigest(),'rows_digest')
    return value

def l7_project(raw,profile):
    p=profile
    l7_need(isinstance(raw,bytes) and len(raw)==p['capture_raw_bytes']
        and hashlib.sha256(raw).hexdigest()==p['capture_raw_sha256'],'raw_binding')
    rows=[{'own_id':own,'tv_id':tv,'matched_observations':0,'qualifying_observations':0,
           'state':'no_observation','scope':'IN_THIS_CAPTURE','witnesses':[],'rejection_reasons':{}}
          for own,tv in p['pairs']]
    by_tv={row['tv_id']:row for row in rows};scope=None;scope_hash=None;seen=set()
    for number,line in enumerate(l7_io.BytesIO(raw),1):
        l7_need(len(line)<=p['max_line_bytes'],'raw_line_bound')
        record=l7_parse(line)
        l7_need(isinstance(record,dict) and record.get('kind') in ('scope','observation','legacy_source','identity','target'),'raw_record_kind')
        if record['kind']=='scope':
            l7_need(number==1 and scope is None,'scope_position')
            l7_need(set(record)=={'kind','cohort','captured_at_utc','database_snapshot','catalog_id_namespace','target_scope','schema_columns'}
                and record['cohort']=='common4-retained' and record['catalog_id_namespace']=='tourvisor_hotel'
                and record['database_snapshot']=='repeatable-read-read-only' and record['target_scope']=='observed_and_legacy_ids'
                and l7_capture_time(record['captured_at_utc']),'scope_header')
            columns=record['schema_columns']
            l7_need(isinstance(columns,dict) and isinstance(columns.get('tour_operator_identity_observations'),list),'scope_schema')
            columns=columns['tour_operator_identity_observations']
            l7_need(all(isinstance(k,str) for k in columns) and len(columns)==len(set(columns))
                and set(L7_COLUMNS)<=set(columns),'scope_schema')
            scope=record;scope_hash=hashlib.sha256(line).hexdigest();continue
        l7_need(scope is not None,'scope_missing')
        if record['kind']!='observation':continue
        row=record.get('row');l7_need(isinstance(row,dict) and 'hotel_id' in row,'observation_shape')
        # A NULL/nonpositive DB ID cannot select a requested hotel. Malformed
        # encodings must not silently become a negative finding for the capture.
        hotel_value=row['hotel_id']
        l7_need(hotel_value is None or type(hotel_value) is int or
            isinstance(hotel_value,str) and re.fullmatch(r'-?(?:0|[1-9][0-9]{0,19})',hotel_value) is not None,
            'hotel_id_encoding')
        hotel=l7_positive(row['hotel_id'])
        if hotel not in by_tv:continue
        l7_need(set(L7_COLUMNS)<=set(row),'observation_columns')
        observation=l7_positive(row['id'])
        l7_need(observation is not None and observation not in seen,'observation_id');seen.add(observation)
        out=by_tv[hotel];out['matched_observations']+=1
        l7_need(out['matched_observations']<=p['max_observations_per_tv'],'observation_bound')
        search=l7_positive(row['search_id']);reasons=[]
        if search is None:reasons.append('search_id_not_positive')
        if row['source'] not in L7_SOURCES:reasons.append('source_not_allowed')
        if not l7_time(row['first_seen_at']):reasons.append('first_seen_at_invalid')
        if not l7_time(row['last_seen_at']):reasons.append('last_seen_at_invalid')
        if reasons:
            for reason in reasons:out['rejection_reasons'][reason]=out['rejection_reasons'].get(reason,0)+1
        else:
            out['qualifying_observations']+=1
            out['witnesses'].append({'observation_id':observation,'hotel_id':hotel,'search_id':search,
                'source':row['source'],'first_seen_at':row['first_seen_at'],'last_seen_at':row['last_seen_at'],
                'line_sha256':hashlib.sha256(line).hexdigest()})
    l7_need(scope is not None,'scope_missing')
    for row in rows:
        row['state']='no_observation' if row['matched_observations']==0 else 'no_qualifying_observation' if row['qualifying_observations']==0 else 'witness_present'
    value={'captured_at_utc':scope['captured_at_utc'],'catalog_id_namespace':'tourvisor_hotel',
        'database_snapshot':'repeatable-read-read-only','scope_line_sha256':scope_hash,
        'required_observation_columns':list(L7_COLUMNS),'rows':rows,'rows_sha256':hashlib.sha256(l7_json(rows)).hexdigest()}
    return l7_validate_projection(value,p)

def l7_production(value):
    return (isinstance(value,dict) and set(value)=={'index.php','v2/index.php','v2/api-v2.php','v2/lead-adapter-v2.php'}
            and all(v is None or l7_hash(v) for v in value.values()))

def l7_parent(prior,reserved,p):
    l7_need(isinstance(prior,dict) and type(prior.get('schema_version')) is int and prior['schema_version']==1
        and prior.get('status')=='complete' and prior.get('mode')=='match-retained-intake'
        and prior.get('operation_id')==p['parent_operation_id'] and l7_sha(prior.get('source_sha')),'parent_identity')
    l7_need(isinstance(reserved,dict) and set(reserved)=={'operation_id','source_sha','mode','reserved_at'}
        and all(reserved[k]==prior[k] for k in ('operation_id','source_sha','mode'))
        and type(reserved['reserved_at']) is int and reserved['reserved_at']>0,'parent_reservation')
    l7_need(all(type(prior.get(k)) is int and prior[k]==0 for k in ('supplier_calls','database_writes','mapping_writes','booking_calls','lead_calls'))
        and prior.get('production_unchanged') is True and l7_production(prior.get('production_before'))
        and prior.get('production_before')==prior.get('production_after'),'parent_read_only')
    material=prior.get('match_retained');l7_need(isinstance(material,dict) and set(material)=={'evidence_sha256','data'},'parent_material')
    data=material['data'];l7_need(isinstance(data,dict) and material['evidence_sha256']==hashlib.sha256(l7_json(data)).hexdigest(),'parent_evidence_digest')
    l7_need(data.get('state')=='captured_and_projected_read_only' and data.get('cohort')=='common4-retained'
        and data.get('operation_id')==prior['operation_id'] and data.get('source_sha')==prior['source_sha']
        and all(type(data.get(k)) is int and data[k]==0 for k in ('supplier_calls','database_writes','mapping_writes')),'parent_capture_identity')
    capture=data.get('capture')
    l7_need(isinstance(capture,dict) and capture.get('state')=='captured_read_only' and capture.get('cohort')=='common4-retained'
        and capture.get('raw_sha256')==p['capture_raw_sha256'] and type(capture.get('raw_bytes')) is int
        and capture['raw_bytes']==p['capture_raw_bytes']
        and all(type(capture.get(k)) is int and capture[k]==0 for k in ('supplier_calls','database_writes','mapping_writes')),'parent_capture_binding')
    return prior['source_sha']

def l7_validate_data(data,command,control,profile=None):
    p=LOCAL7_REGISTRATION if profile is None else profile
    l7_command(command,control,p)
    keys=L7_PROJECTION|set(L7_ZERO)|set(L7_FALSE)|{'schema_version','state','operation_id','source_sha',
        'control_source_sha','cohort','target_operation_id','parent_source_sha','parent_receipt_sha256',
        'parent_reservation_sha256','capture_raw_sha256','capture_raw_bytes','max_observations_per_tv',
        'protected_before','protected_after','protected_inputs_unchanged','capture_only','no_replay'}
    l7_need(isinstance(data,dict) and set(data)==keys,'data_shape')
    fixed={'schema_version':'local7_registration_evidence_v1','state':'retained_registration_evidence_read_only',
        'operation_id':p['operation_id'],'source_sha':p['source_sha'],'control_source_sha':control,
        'cohort':p['cohort'],'target_operation_id':p['parent_operation_id'],
        'parent_receipt_sha256':p['parent_receipt_sha256'],'capture_raw_sha256':p['capture_raw_sha256']}
    l7_need(all(data[k]==v for k,v in fixed.items()) and l7_sha(data['parent_source_sha']),'data_binding')
    l7_need(type(data['capture_raw_bytes']) is int and data['capture_raw_bytes']==p['capture_raw_bytes']
        and type(data['max_observations_per_tv']) is int and data['max_observations_per_tv']==p['max_observations_per_tv'],'data_bounds')
    l7_need(all(type(data[k]) is int and data[k]==0 for k in L7_ZERO)
        and all(data[k] is False for k in L7_FALSE)
        and all(data[k] is True for k in ('no_replay','capture_only','protected_inputs_unchanged')),'data_authority')
    before=data['protected_before'];bounds={'result.json':32*1024*1024,'reservation.json':65536,'retained-raw.ndjson':134217728}
    l7_need(isinstance(before,dict) and set(before)==set(bounds) and data['protected_after']==before,'protected_inputs')
    for pins in (before,data['protected_after']):
        for name,bound in bounds.items():
            pin=pins[name]
            l7_need(isinstance(pin,dict) and set(pin)=={'bytes','sha256'} and type(pin['bytes']) is int
                and 0<pin['bytes']<=bound and l7_hash(pin['sha256']),'protected_input_shape')
    l7_need(before['result.json']['sha256']==p['parent_receipt_sha256']
        and before['retained-raw.ndjson']=={'bytes':p['capture_raw_bytes'],'sha256':p['capture_raw_sha256']}
        and before['reservation.json']['sha256']==data['parent_reservation_sha256'],'protected_input_binding')
    l7_validate_projection({k:data[k] for k in L7_PROJECTION},p)
    l7_need(len(l7_json(data))<=1048576,'result_bound')
    return data
'''
exec(LOCAL7_VALIDATOR)

LOCAL7_HANDLER = r'''
def retained_local7_registration(stage):
    import stat as l7_stat
    p=LOCAL7_REGISTRATION;control=payload.get('match_retained_control_sha')
    command={k:payload.get(k) for k in L7_COMMAND}
    l7_command(command,control,p)
    l7_need(mode==p['mode'] and operation==p['operation_id'] and source==p['source_sha'],'remote_identity')
    target=private/p['parent_operation_id']
    l7_need(private.is_dir() and not private.is_symlink() and private.resolve()==private
        and target.is_dir() and not target.is_symlink() and target.resolve()==target,'parent_directory')
    bounds={'result.json':32*1024*1024,'reservation.json':65536,'retained-raw.ndjson':134217728}
    signatures={};protected={}
    def signature(st):
        return (st.st_dev,st.st_ino,st.st_mode,st.st_nlink,st.st_size,st.st_mtime_ns,st.st_ctime_ns)
    def read(name,remember):
        path=target/name;bound=bounds[name];before=path.lstat()
        l7_need(l7_stat.S_ISREG(before.st_mode) and before.st_nlink==1 and path.resolve()==path
            and 0<before.st_size<=bound,'protected_path')
        fd=os.open(path,os.O_RDONLY|os.O_NOFOLLOW|os.O_NONBLOCK)
        with os.fdopen(fd,'rb') as handle:
            opened=os.fstat(handle.fileno())
            l7_need(signature(opened)==signature(before),'protected_open_drift')
            raw=handle.read(bound+1);after=os.fstat(handle.fileno())
        named=path.lstat()
        l7_need(signature(before)==signature(after)==signature(named) and len(raw)==named.st_size
            and path.resolve()==path,'protected_read_drift')
        value={'bytes':len(raw),'sha256':hashlib.sha256(raw).hexdigest()}
        if remember:signatures[name]=signature(named);protected[name]=value
        else:l7_need(signatures[name]==signature(named) and protected[name]==value,'protected_input_drift')
        return raw,value
    parent_raw,_=read('result.json',True)
    l7_need(protected['result.json']['sha256']==p['parent_receipt_sha256'],'parent_receipt_pin')
    reservation_raw,_=read('reservation.json',True)
    parent_source=l7_parent(l7_parse(parent_raw),l7_parse(reservation_raw),p)
    raw,_=read('retained-raw.ndjson',True)
    projection=l7_project(raw,p)
    after={}
    for name in bounds:_,after[name]=read(name,False)
    l7_need(target.is_dir() and not target.is_symlink() and target.resolve()==target,'parent_directory_drift')
    data=dict(projection)
    data.update(schema_version='local7_registration_evidence_v1',state='retained_registration_evidence_read_only',
        operation_id=operation,source_sha=source,control_source_sha=control,cohort=p['cohort'],
        target_operation_id=p['parent_operation_id'],parent_source_sha=parent_source,
        parent_receipt_sha256=p['parent_receipt_sha256'],parent_reservation_sha256=protected['reservation.json']['sha256'],
        capture_raw_sha256=p['capture_raw_sha256'],capture_raw_bytes=p['capture_raw_bytes'],
        max_observations_per_tv=p['max_observations_per_tv'],protected_before=protected,protected_after=after,
        protected_inputs_unchanged=True,capture_only=True,no_replay=True)
    data.update({k:0 for k in L7_ZERO});data.update({k:False for k in L7_FALSE})
    l7_validate_data(data,command,control,p)
    evidence=l7_json(data)
    fd=os.open(op/'retained-evidence.json',os.O_WRONLY|os.O_CREAT|os.O_EXCL|os.O_NOFOLLOW,0o600)
    with os.fdopen(fd,'wb') as handle:handle.write(evidence);handle.flush();os.fsync(handle.fileno())
    return {'evidence_sha256':hashlib.sha256(evidence).hexdigest(),'data':data}
'''

LOCAL7_DISPATCH = r'''    if mode=='match-retained-readback':
        result['supplier_calls']=result['database_writes']=result['mapping_writes']=0
        result['match_retained']=retained_local7_registration(stage)
        result['production_after']=fingerprints()
        if result['production_after']!=before:fail('production_drift')
        result['production_unchanged']=True;result['status']='complete'
'''

def completed_local7_registration(command,result,control):
    try:
        keys={'schema_version','operation_id','source_sha','mode','status','supplier_calls','database_writes',
            'booking_calls','lead_calls','production_before','production_after','production_unchanged','mapping_writes','match_retained'}
        if not isinstance(result,dict) or set(result)!=keys or type(result['schema_version']) is not int or result['schema_version']!=1:return False
        if result['status']!='complete' or result['production_unchanged'] is not True:return False
        if any(result[k]!=command.get(k) for k in ('operation_id','source_sha','mode')):return False
        if any(type(result[k]) is not int or result[k]!=0 for k in ('supplier_calls','database_writes','mapping_writes','booking_calls','lead_calls')):return False
        if not l7_production(result['production_before']) or result['production_before']!=result['production_after']:return False
        material=result['match_retained']
        if not isinstance(material,dict) or set(material)!= {'evidence_sha256','data'}:return False
        data=l7_validate_data(material['data'],command,control)
        return material['evidence_sha256']==hashlib.sha256(l7_json(data)).hexdigest()
    except (KeyError,TypeError,ValueError,AttributeError,OverflowError):
        return False

def activate_local7_registration(core,command):
    expected=core.parse_command(core.PREFIX+' '.join(str(command.get(k,'')) for k in L7_COMMAND))
    need(command==expected,'local7_command_shape')
    control=os.environ.get('GITHUB_SHA','');l7_command(command,control,LOCAL7_REGISTRATION)
    need(os.environ.get('GITHUB_RUN_ATTEMPT','1')=='1','retained_first_attempt_only')
    command['match_retained_control_sha']=control
    remote=core.REMOTE
    anchors={'definition':'def run_match942(stage, mode, offset, limit):\n',
        'dispatch':"    if mode=='match-tv942-write':\n",'collector':"    if mode not in ('reconcile',",
        'manifest':"    if not isinstance(files,dict) or len(files)<20: fail('manifest')",
        'reservation':'    op.mkdir(mode=0o700)\n','receipt':"if 'op' in globals() and op.exists():"}
    for key,anchor in anchors.items():
        need(remote.count(anchor)==(2 if key=='collector' else 1),'local7_registration_drift_'+key)
    handler='\nLOCAL7_REGISTRATION='+repr(LOCAL7_REGISTRATION)+'\n'+LOCAL7_VALIDATOR+LOCAL7_HANDLER
    remote=remote.replace(anchors['definition'],handler+'\n'+anchors['definition'],1)
    remote=remote.replace(anchors['dispatch'],LOCAL7_DISPATCH+anchors['dispatch'],1)
    remote=remote.replace(anchors['collector'],"    if mode not in ('match-retained-readback','reconcile',")
    remote=remote.replace(anchors['manifest'],"    if not isinstance(files,dict) or files!="+repr(SOURCE_HASHES)+": fail('retained_manifest')",1)
    remote=remote.replace(anchors['reservation'],anchors['reservation']+'    retained_reserved=True\n',1)
    remote=remote.replace(anchors['receipt'],"if globals().get('retained_reserved') is True and op.exists():",1)
    ast.parse(remote)
    core.REMOTE=remote;core.bundle_source=bundle_source
    original=core.completed_result
    core.completed_result=lambda cmd,res:completed_local7_registration(cmd,res,control) if (
        cmd.get('operation_id')==LOCAL7_REGISTRATION['operation_id'] or cmd.get('cohort')==LOCAL7_REGISTRATION['cohort']) else original(cmd,res)
