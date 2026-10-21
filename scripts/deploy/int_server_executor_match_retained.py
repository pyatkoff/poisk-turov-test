"""Reviewed retained-evidence intake in the stock carrier; no new SSH/auth route."""
from __future__ import annotations
import ast
import copy
import datetime as dt
import hashlib
import io
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
    if cohort in MODES.values():
        return NEXT_SCOPE
    return catalogue_snapshot()[1].get(cohort,NEXT_SCOPE)


def register_parser(core):
    original = core.parse_command
    def parse(body):
        if not body.startswith(core.PREFIX):
            return original(body)
        parts = body[len(core.PREFIX):].split()
        if len(parts) < 2 or parts[1] not in MODES:
            return original(body)
        if len(parts) >= 4 and parts[3] in (BINDING_RECONCILIATION['cohort'], BINDING_RECONCILIATION_V2['cohort']):
            profile = BINDING_RECONCILIATION_V2 if parts[3] == BINDING_RECONCILIATION_V2['cohort'] else BINDING_RECONCILIATION
            need(len(parts) == 5 and parts[1] == 'match-retained-readback'
                 and parts[2] == profile['operation_id']
                 and parts[4] == profile['parent_operation_id'], 'binding_reconciliation_scope')
            need(core.SHA_RE.fullmatch(parts[0]) is not None, 'retained_source_sha')
            need(os.environ.get('GITHUB_RUN_ATTEMPT', '1') == '1', 'retained_first_attempt_only')
            return dict(source_sha=parts[0], mode=parts[1], operation_id=parts[2],
                        cohort=parts[3], target_operation_id=parts[4])
        if len(parts) >= 4 and parts[3] in (OPERATOR_BINDING['cohort'], OPERATOR_BINDING_FULL['cohort']):
            profile = OPERATOR_BINDING_FULL if parts[3] == OPERATOR_BINDING_FULL['cohort'] else OPERATOR_BINDING
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

def current_retained_target(stage):
    name=payload.get('target_operation_id','')
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
    if ((payload.get('cohort')!=RETAINED_MODES.get(mode) and not (payload.get('cohort')==NEXT_SCOPE['cohort'] and mode in ('match-retained-acquire','match-retained-anex-write-v2','match-retained-readback')))
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


def completed_result(command, result, control_sha):
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
    reconciliation_v2 = command.get('cohort') == BINDING_RECONCILIATION_V2['cohort']
    reconciliation = command.get('cohort') == BINDING_RECONCILIATION['cohort'] or reconciliation_v2
    binding_full = command.get('cohort') == OPERATOR_BINDING_FULL['cohort']
    binding = command.get('cohort') == OPERATOR_BINDING['cohort'] or binding_full
    policy = BINDING_RECONCILIATION_V2 if reconciliation_v2 else BINDING_RECONCILIATION if reconciliation else OPERATOR_BINDING_FULL if binding_full else OPERATOR_BINDING if binding else continuation_policy(command.get('cohort'))
    catalogue='catalogue_sha256' in policy
    handler = REMOTE_HANDLER
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
    remote=remote.replace(anchors['definition'],'RETAINED_MODES='+repr(MODES)+'\nNEXT_SCOPE='+repr(policy)+'\nSAVED_DETAIL_READBACKS='+repr(SAVED_DETAIL_READBACKS)+'\nTRANSPORT_DIAGNOSTIC='+repr(TRANSPORT_DIAGNOSTIC)+'\nPROOF_SOURCE_INSPECTION='+repr(PROOF_SOURCE_INSPECTION)+'\n'+handler+'\n'+anchors['definition'],1)
    remote=remote.replace(anchors['dispatch'],REMOTE_DISPATCH+anchors['dispatch'],1)
    remote=remote.replace(anchors['collector'],"    if mode not in ('match-retained-intake','match-tourvisor-quota-policy','match-retained-readback','match-retained-project','match-retained-current','match-retained-acquire','match-retained-anex-write','match-retained-anex-write-v2','reconcile',")
    hashes=dict(SOURCE_HASHES)
    if catalogue:hashes[CATALOGUE_PATH]=policy['catalogue_sha256']
    remote=remote.replace(anchors['manifest'],"    if not isinstance(files,dict) or files!="+repr(hashes)+": fail('retained_manifest')",1)
    reservation='    op.mkdir(mode=0o700)\n'
    receipt="if 'op' in globals() and op.exists():"
    need(remote.count(reservation)==1 and remote.count(receipt)==1,'retained_reservation_anchor')
    remote=remote.replace(reservation,reservation+'    retained_reserved=True\n',1)
    remote=remote.replace(receipt,"if globals().get('retained_reserved') is True and op.exists():",1)
    ast.parse(remote)
    core.REMOTE=remote;core.bundle_source=lambda source_root:bundle_source(source_root,catalogue=catalogue)
    original=core.completed_result
    core.completed_result=lambda cmd,res: completed_result(cmd,res,control) if cmd.get('mode') in MODES else original(cmd,res)


