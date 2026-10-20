"""Finite missing-ANEX acquisition; raw HTTP precedes parsing, no retries/writes."""
from __future__ import annotations
import collections
import fcntl
import datetime as dt
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request
import hotel_match_live30_common4_continuation_acquire_v10 as shape
from hotel_match_tourvisor_quota_v1 import Quota

PARENT_EVIDENCE = 'c0d7c1f53cceea267f08a5b15d29d9f2bb314401b7de380e0fe40b59ad513046'
SELECTED_IDS_SHA256 = '4f8fa702fad1445d2a6bb5978bbdc1e60f30abd75b9328251f381a86f2b351d3'
BASE = 'https://api.tourvisor.ru/search/api/v1'
BODY_LIMIT = 16*1024*1024
SCOPE_FILE = 'scripts/diagnostics/fixtures/hotel_match_retained_next_scope_20261020.json'
TIMEOUT60_SCOPE_FILE = 'scripts/diagnostics/fixtures/hotel_match_retained_timeout60_scope_20261020.json'
CATALOGUE_FILE = 'scripts/deploy/fixtures/hotel_match_retained_scope_catalogue_v1.json'


def canonical(value):
    return json.dumps(value, sort_keys=True, separators=(',', ':')).encode()


def load_scope_file(source_root, data):
    """Read only a scope pinned by the stock carrier's exact source manifest."""
    scope=data['continuation_scope']
    if 'continuation_catalogue_sha256' in data:
        catalogue_digest=data['continuation_catalogue_sha256']
        member_digest=data.get('continuation_scope_sha256')
        if (not isinstance(scope,dict) or data.get('continuation_scope_path')!=CATALOGUE_FILE
                or not isinstance(catalogue_digest,str) or re.fullmatch(r'[a-f0-9]{64}',catalogue_digest) is None
                or not isinstance(member_digest,str) or re.fullmatch(r'[a-f0-9]{64}',member_digest) is None):
            raise ValueError('continuation_catalogue_binding')
        path=Path(source_root)/CATALOGUE_FILE;manifest_path=Path(source_root)/'manifest.json'
        for file,bound in ((path,1024*1024),(manifest_path,65536)):
            if (file.is_symlink() or not file.is_file() or file.resolve()!=file
                    or file.stat().st_nlink!=1 or not 0<file.stat().st_size<=bound):
                raise ValueError('continuation_catalogue_path')
        def unique_object(pairs):
            value={}
            for key,item in pairs:
                if key in value:raise ValueError('continuation_catalogue_duplicate_key')
                value[key]=item
            return value
        manifest=json.loads(manifest_path.read_bytes(),object_pairs_hook=unique_object)
        raw=path.read_bytes();digest=hashlib.sha256(raw).hexdigest()
        if (not isinstance(manifest,dict) or set(manifest)!={'schema_version','files'}
                or type(manifest.get('schema_version')) is not int or manifest['schema_version']!=1
                or not isinstance(manifest.get('files'),dict)
                or manifest['files'].get(CATALOGUE_FILE)!=digest or digest!=catalogue_digest):
            raise ValueError('continuation_catalogue_binding')
        catalogue=json.loads(raw,object_pairs_hook=unique_object)
        if (not isinstance(catalogue,dict) or set(catalogue)!={'schema_version','kind','scopes'}
                or type(catalogue.get('schema_version')) is not int or catalogue['schema_version']!=1
                or catalogue.get('kind')!='retained_never_selected_catalogue'
                or not isinstance(catalogue.get('scopes'),list) or not 1<=len(catalogue['scopes'])<=100):
            raise ValueError('continuation_catalogue_shape')
        cohorts=set();identities=set();matches=[]
        for item in catalogue['scopes']:
            if (not isinstance(item,dict) or not isinstance(item.get('cohort'),str)
                    or not isinstance(item.get('scope_id'),str)
                    or re.fullmatch(r'[a-f0-9]{64}',item['scope_id']) is None
                    or item['cohort']!='anex-next-'+item['scope_id'][:16]
                    or item['cohort'] in cohorts or item['scope_id'] in identities
                    or item.get('scope_kind')!='never_selected_bounded'
                    or not isinstance(item.get('selected_tv_hotel_ids'),list)
                    or not 1<=len(item['selected_tv_hotel_ids'])<=25
                    or any(type(hid) is not int or hid<1 for hid in item['selected_tv_hotel_ids'])
                    or item['selected_tv_hotel_ids']!=sorted(set(item['selected_tv_hotel_ids']))
                    or type(item.get('selected_count')) is not int
                    or item['selected_count']!=len(item['selected_tv_hotel_ids'])):
                raise ValueError('continuation_catalogue_member')
            cohorts.add(item['cohort']);identities.add(item['scope_id'])
            if item['cohort']==scope.get('cohort') and item['scope_id']==scope.get('scope_id'):
                matches.append(item)
        if len(matches)!=1 or matches[0]!=scope or hashlib.sha256(canonical(matches[0])).hexdigest()!=member_digest:
            raise ValueError('continuation_catalogue_member_binding')
        return scope
    relative=data.get('continuation_scope_path') or (TIMEOUT60_SCOPE_FILE if scope.get('context_origin')=='owner_20261020_1257_timeout60_new_window' else SCOPE_FILE)
    if not isinstance(relative,str) or not re.fullmatch(r'scripts/diagnostics/fixtures/hotel_match_retained_[a-z0-9_]+_scope_[0-9]{8}\.json',relative):
        raise ValueError('continuation_scope_path')
    path=Path(source_root)/relative;manifest_path=Path(source_root)/'manifest.json'
    for file,bound in ((path,262144),(manifest_path,65536)):
        if (file.is_symlink() or not file.is_file() or file.resolve()!=file or file.stat().st_nlink!=1 or file.stat().st_size>bound):
            raise ValueError('continuation_scope_path')
    manifest=json.loads(manifest_path.read_bytes());raw=path.read_bytes();digest=hashlib.sha256(raw).hexdigest()
    if (manifest.get('schema_version')!=1 or manifest.get('files',{}).get(relative)!=digest
            or digest!=data.get('continuation_scope_sha256') or json.loads(raw)!=scope):
        raise ValueError('continuation_scope_binding')
    return scope


def context_identity(scope, hotel):
    return dict(namespace='anex_online',account=scope['account'],operator_id=13,tv_hotel_id=hotel,
                country_id=scope['country_id'],departure_id=scope['departure_id'],adults=scope['adults'],
                childs=[],**scope['wire_context'])


def check_new_context(scope, previous):
    wire=scope.get('wire_context',{})
    if (scope.get('scope_kind')!='owner_date_variation' or scope.get('context_origin') not in ('owner_20261020_1031_saved_peer_context','owner_20261020_1257_timeout60_new_window')
            or set(wire)!=set(('date_from','date_to','nights_from','nights_to'))
            or any(not isinstance(wire.get(k),str) or dt.date.fromisoformat(wire[k]).isoformat()!=wire[k] for k in ('date_from','date_to'))
            or not dt.datetime.now(dt.timezone.utc).date().isoformat()<=wire['date_from']<=wire['date_to']
            or (dt.date.fromisoformat(wire['date_to'])-dt.date.fromisoformat(wire['date_from'])).days>6
            or any(type(wire.get(k)) is not int for k in ('nights_from','nights_to'))
            or not 1<=wire['nights_from']<=wire['nights_to']<=28
            or scope.get('search_start_maximum')!=1 or scope.get('supplier_http_maximum')!=30):
        raise ValueError('date_context_policy')
    if (scope['context_origin']=='owner_20261020_1257_timeout60_new_window'
            and (scope.get('owner_authorized_at')!='2026-10-20T12:57:13+02:00' or scope.get('http_timeout_seconds')!=60)):
        raise ValueError('date_context_owner_authority')
    ids=set(scope['selected_tv_hotel_ids'])
    for record in previous:
        p=record['params']
        if (p['countryId']==scope['country_id'] and p['departureId']==scope['departure_id']
                and p['adults']==scope['adults'] and p['childs']==[] and 13 in p['operatorIds']
                and ids.intersection(p['hotelIds'])
                and wire['date_from']<=p['dateTo'] and p['dateFrom']<=wire['date_to']):
            raise ValueError('effective_date_range_consumed_no_replay')


def reserve_contexts(home, scope, operation, previous):
    """Append context claims; never mutate/recreate the original hotel claims."""
    check_new_context(scope,previous)
    root=Path(home)/'.anytoour-match';hotel=root/'retained-selection-claims.json'
    if not root.is_dir() or root.is_symlink() or root.resolve()!=root:raise ValueError('selection_root')
    with os.fdopen(os.open(hotel,os.O_RDONLY|os.O_NOFOLLOW),'rb') as saved:
        fcntl.flock(saved,fcntl.LOCK_SH)
        if os.fstat(saved.fileno()).st_nlink!=1:raise ValueError('selection_ledger_path')
        raw=saved.read(8*1024*1024+1)
        if len(raw)>8*1024*1024:raise ValueError('selection_ledger_path')
        old=json.loads(raw)
        if (old.get('schema_version')!=1 or any('TOURVISOR_ANEX_JWT:13:'+str(hid) not in old.get('claims',{})
                                               for hid in scope['prior_selected_hotel_ids'])):
            raise ValueError('selection_prior_claim_missing')
        path=root/'retained-context-claims.json'
        with os.fdopen(os.open(path,os.O_RDWR|os.O_CREAT|os.O_NOFOLLOW,0o600),'r+b') as stream:
            fcntl.flock(stream,fcntl.LOCK_EX)
            if os.fstat(stream.fileno()).st_nlink!=1 or os.fstat(stream.fileno()).st_size>8*1024*1024:raise ValueError('context_ledger_path')
            body=stream.read();data=json.loads(body) if body else {'schema_version':1,'claims':{}}
            if data.get('schema_version')!=1 or not isinstance(data.get('claims'),dict):raise ValueError('context_ledger_shape')
            for hid in scope['selected_tv_hotel_ids']:
                item=context_identity(scope,hid);key=hashlib.sha256(canonical(item)).hexdigest()
                for existing in data['claims'].values():
                    c=existing['context']
                    if (all(c.get(k)==item[k] for k in ('account','namespace','operator_id','tv_hotel_id','country_id','departure_id','adults','childs'))
                            and item['date_from']<=c['date_to'] and c['date_from']<=item['date_to']):
                        raise ValueError('context_consumed_no_replay')
                data['claims'][key]={'context':item,'operation_id':operation,'scope_id':scope['scope_id'],'state':'reserved_before_http_no_replay'}
            encoded=canonical(data);stream.seek(0);stream.write(encoded);stream.truncate();stream.flush();os.fsync(stream.fileno())
            stream.seek(0)
            if stream.read()!=encoded:raise ValueError('context_reservation_unknown_no_replay')
            fd=os.open(root,os.O_RDONLY|os.O_DIRECTORY)
            try:os.fsync(fd)
            finally:os.close(fd)
        saved.seek(0)
        if saved.read()!=raw:raise ValueError('hotel_ledger_changed')
    return {'context_reservation_sha256':hashlib.sha256(encoded).hexdigest(),'hotel_ledger_sha256':hashlib.sha256(raw).hexdigest()}


def inspect_saved_detail(detail, hotel):
    """Structure only: never emit original URL, query values or opaque path tokens."""
    value=native_pair(detail,hotel);url=detail.get('operatorLink') if isinstance(detail,dict) else None
    out={'proof':value,'operator_link_type':type(url).__name__}
    if isinstance(url,str):
        parts=urllib.parse.urlsplit(url);keys=urllib.parse.parse_qsl(parts.query,keep_blank_values=True)
        host=(parts.hostname or '').lower()
        out.update(operator_link_sha256=hashlib.sha256(url.encode()).hexdigest(),
                   recognized_anex_host=bool(re.fullmatch(r'(?:[a-z0-9-]+\.)*(?:anextour\.ru|anextour\.com)',host)),
                   host=host if re.fullmatch(r'(?:[a-z0-9-]+\.)*(?:anextour\.ru|anextour\.com)',host) else 'unrecognized',
                   path_sha256=hashlib.sha256(parts.path.encode()).hexdigest(),
                   path_shape=[x if x in ('hotel','hotels','search','tour','tours','online','index.php','search.php') else 'digits:'+str(len(x)) if x.isascii() and x.isdigit() else 'opaque:'+str(len(x)) for x in parts.path.split('/') if x],
                   query_key_shapes=[k if re.fullmatch(r'[A-Za-z_][A-Za-z_0-9]{0,40}',k) and not shape.SECRET.search(k) else 'redacted' for k,v in keys],
                   query_value_shapes=['digits:'+str(len(v)) if v.isascii() and v.isdigit() else 'opaque:'+str(len(v)) for k,v in keys],
                   html_entity_present='&amp;' in url,fragment_present=bool(parts.fragment),userinfo_present=bool(parts.username or parts.password))
        selectors=[]
        if not any(shape.SECRET.search(k or '') for k,v in keys):
            for key,text in keys:
                if key.upper() not in ('HOTELLIST','TOWNFROM'):continue
                item={'key':key.upper(),'format':'non_identifier_text','positive_tokens':[],
                      'all_tokens_preserved':True,'native_namespace_authority':False}
                if re.fullmatch(r'[1-9][0-9]{0,19}',text):
                    item.update(format='decimal_scalar',positive_tokens=[int(text)])
                else:
                    try:parsed=json.loads(text)
                    except (ValueError,TypeError):parsed=None
                    if isinstance(parsed,list) and 1<=len(parsed)<=30 and all(type(x) is int and x>0 for x in parsed):
                        item.update(format='json_positive_integer_array',positive_tokens=parsed)
                    elif isinstance(parsed,list):item['format']='json_array_not_all_positive_integers'
                    elif isinstance(parsed,str):item['format']='json_string_not_native_decimal'
                item['multiple_tokens']=len(item['positive_tokens'])>1
                item['outside_existing_direct_anex_id_bound']=any(x>99999999 for x in item['positive_tokens'])
                selectors.append(item)
        out['selector_analysis']=selectors
    return out


def select_scope_rows(scope, current, today=None):
    """Select a reviewed, immutable, never-selected roster; old105 is separate."""
    today = today or dt.datetime.now(dt.timezone.utc).date().isoformat()
    variation=scope.get('scope_kind')=='owner_date_variation'
    fresh=scope.get('scope_kind')=='never_selected_bounded'
    bounded=variation or fresh
    ids = scope.get('selected_tv_hotel_ids')
    prior = scope.get('prior_selected_hotel_ids')
    if (type(scope.get('schema_version')) is not int or scope['schema_version'] != 1
            or scope.get('account') != 'TOURVISOR_ANEX_JWT' or scope.get('operator_id') != 13
            or not isinstance(ids, list) or not 1 <= len(ids) <= 105
            or any(type(v) is not int or v < 1 for v in ids) or ids != sorted(set(ids))
            or type(scope.get('selected_count')) is not int or len(ids) != scope['selected_count']
            or not isinstance(prior, list) or any(type(v) is not int or v < 1 for v in prior)
            or prior != sorted(set(prior)) or (not variation and set(ids).intersection(prior))
            or scope.get('supplier_http_maximum') != (30 if bounded else 400)
            or (variation and (len(ids)>25 or 8038 in ids or not set(ids).issubset(prior)))
            or (fresh and (len(ids)>25 or scope.get('search_start_maximum')!=1
                           or scope.get('http_timeout_seconds')!=60
                           or scope.get('context_grouping')!='exact_date_nights'
                           or scope.get('context_origin')!='saved_user_search_exact_date_nights'))
            or scope.get('safe_to_write_now') is not False
            or any(type(scope.get(k)) is not int or scope[k] != 0 for k in ('database_writes','mapping_writes'))):
        raise ValueError('continuation_scope_shape')
    for field in ('country_id','departure_id','adults'):
        if type(scope.get(field)) is not int or scope[field] < 1:
            raise ValueError('continuation_party')
    if (type(scope.get('children_count')) is not int or scope['children_count'] != 0 or scope.get('child_ages_signature') != ''
            or scope['adults'] > 6):
        raise ValueError('continuation_party')
    identity_keys=('parent_evidence_sha256','selected_tv_hotel_ids','future_contexts')+(('scope_kind','wire_context','context_origin') if bounded else ())
    identity = hashlib.sha256(canonical({k: scope[k] for k in identity_keys})).hexdigest()
    if scope.get('scope_id') != identity or scope.get('cohort') != 'anex-next-'+identity[:16]:
        raise ValueError('continuation_scope_digest')
    if hashlib.sha256(canonical(current)).hexdigest() != scope.get('parent_evidence_sha256'):
        raise ValueError('continuation_parent_evidence')
    rows = {r['tv_hotel_id']: r for r in current['rows']}
    if variation and scope.get('context_origin')=='owner_20261020_1031_saved_peer_context':
        peer=rows.get(scope.get('context_peer_tv_hotel_id'),{})
        ctx=peer.get('future_context',{})
        if (hashlib.sha256(canonical(peer)).hexdigest()!=scope.get('context_peer_row_sha256')
                or ctx.get('source')!='user_search' or any(ctx.get(k)!=scope[k] for k in ('country_id','departure_id','adults','children_count'))
                or ctx.get('child_ages_signature')!='' or scope['wire_context']!={'date_from':ctx.get('departure_date'),'date_to':ctx.get('departure_date'),'nights_from':ctx.get('nights'),'nights_to':ctx.get('nights')}):
            raise ValueError('date_context_peer_provenance')
    if variation:check_new_context(scope,[])
    selected = []
    for hid in ids:
        row = rows.get(hid); ctx = (row or {}).get('future_context') or {}; target = (row or {}).get('target') or {}
        if (not row or row.get('acquisition_ready') is not True or row.get('direct_anex_native_ids')
                or row.get('anchor_state') != 'canonical_anchor_ok'
                or hashlib.sha256(canonical(row)).hexdigest() != scope.get('current_row_sha256',{}).get(str(hid))
                or ctx != scope.get('future_contexts',{}).get(str(hid))
                or ctx.get('hotel_id') != hid or target.get('country_id') != scope['country_id']
                or any(type(ctx.get(k)) is not int or ctx[k] != scope[k] for k in
                       ('country_id','departure_id','adults','children_count'))
                or ctx.get('child_ages_signature') != scope['child_ages_signature']
                or type(ctx.get('nights')) is not int or not 1 <= ctx['nights'] <= 28
                or not isinstance(ctx.get('departure_date'),str)
                or dt.date.fromisoformat(ctx['departure_date']).isoformat() != ctx['departure_date']
                or ctx['departure_date'] < today):
            raise ValueError('continuation_row_binding')
        selected.append(row)
    if fresh:
        wire=scope.get('wire_context')
        if any(r['future_context'].get('source')!='user_search' or wire!={
                'date_from':r['future_context']['departure_date'],'date_to':r['future_context']['departure_date'],
                'nights_from':r['future_context']['nights'],'nights_to':r['future_context']['nights']}
               for r in selected):
            raise ValueError('never_selected_exact_saved_context')
    return selected


def configure_scope_budget(client, scope):
    """Only reviewed bounded scopes lower the legacy client budget."""
    if scope and scope.get('scope_kind') in ('owner_date_variation','never_selected_bounded'):
        client.http_maximum=30;client.search_start_maximum=1


def check_never_selected_requests(scope, previous):
    """Hotel-only new intake must never overlap even an UNKNOWN old request."""
    ids=set(scope['selected_tv_hotel_ids'])
    if any(ids.intersection(record['params']['hotelIds']) for record in previous):
        raise ValueError('selection_effective_request_consumed_no_replay')


def reserve_selection(home, scope, operation):
    """Account/operator targets remain consumed across operation names and days."""
    root = Path(home)/'.anytoour-match'
    if not root.is_dir() or root.is_symlink() or root.resolve() != root:
        raise ValueError('selection_root')
    path = root/'retained-selection-claims.json'
    # A continuation after the bootstrap must retain the original durable ledger.
    flags = os.O_RDWR|os.O_NOFOLLOW
    if 'exclusion_history' not in scope:
        flags |= os.O_CREAT
    fd = os.open(path, flags, 0o600)
    with os.fdopen(fd,'r+b') as stream:
        fcntl.flock(stream,fcntl.LOCK_EX)
        if os.fstat(stream.fileno()).st_nlink != 1 or os.fstat(stream.fileno()).st_size > 8*1024*1024:
            raise ValueError('selection_ledger_path')
        raw = stream.read(); data = json.loads(raw) if raw else {'schema_version':1,'claims':{}}
        if data.get('schema_version') != 1 or not isinstance(data.get('claims'),dict):
            raise ValueError('selection_ledger_shape')
        claims = data['claims']
        if 'exclusion_history' in scope and any('TOURVISOR_ANEX_JWT:13:'+str(hid) not in claims
                                                for hid in scope['prior_selected_hotel_ids']):
            raise ValueError('selection_prior_claim_missing')
        for hid in scope['selected_tv_hotel_ids']:
            if 'TOURVISOR_ANEX_JWT:13:'+str(hid) in claims:
                raise ValueError('selection_consumed_no_replay')
        for hid in scope['prior_selected_hotel_ids']:
            claims.setdefault('TOURVISOR_ANEX_JWT:13:'+str(hid),
                              {'operation_id':scope['prior_acquisition_operation_id'],'state':'prior_consumed'})
        for hid in scope['selected_tv_hotel_ids']:
            claims['TOURVISOR_ANEX_JWT:13:'+str(hid)] = {
                'operation_id':operation,'scope_id':scope['scope_id'],'state':'reserved_before_http_no_replay'}
        encoded = canonical(data)
        stream.seek(0);stream.write(encoded);stream.truncate();stream.flush();os.fsync(stream.fileno())
        stream.seek(0)
        if stream.read() != encoded:
            raise ValueError('selection_reservation_unknown_no_replay')
        dirfd=os.open(root,os.O_RDONLY|os.O_DIRECTORY)
        try:os.fsync(dirfd)
        finally:os.close(dirfd)
    return hashlib.sha256(encoded).hexdigest()


def save_bytes(path, raw):
    with path.open('xb') as handle:
        os.chmod(path, 0o600)
        if handle.write(raw) != len(raw):
            raise ValueError('raw_short_write')
        handle.flush(); os.fsync(handle.fileno())
    return hashlib.sha256(raw).hexdigest()


def save_json(path, value):
    return save_bytes(path, json.dumps(value, sort_keys=True, separators=(',', ':')).encode())


def select_rows(data):
    selected = []
    for row in data['rows']:
        h, ctx = row.get('target') or {}, row.get('future_context') or {}
        if (row.get('acquisition_ready') is True and not row.get('direct_anex_native_ids')
                and row.get('anchor_state') == 'canonical_anchor_ok'
                and int(h.get('country_id', 0)) == int(ctx.get('country_id', 0)) == 4
                and int(ctx.get('departure_id', 0)) == 1 and int(ctx.get('adults', 0)) == 2
                and int(ctx.get('children_count', -1)) == 0 and ctx.get('child_ages_signature') == ''):
            selected.append(row)
    return sorted(selected, key=lambda x: x['tv_hotel_id'])


def native_pair(detail, hotel):
    if shape.ident(detail, 'hotel') != hotel or shape.ident(detail, 'operator') != 13:
        return {'state': 'detail_identity_mismatch'}
    url = detail.get('operatorLink')
    parsed = shape.native_projection(13, url)
    if parsed.get('link_state') != 'captured_single_native':
        return {'state': 'native_link_unresolved', 'link_state': parsed.get('link_state')}
    parts = urllib.parse.urlsplit(url)
    keys = {k.lower() for k, _ in urllib.parse.parse_qsl(parts.query, keep_blank_values=True)}
    native = parsed['positive_native_candidates'][0]
    if not keys.intersection({'hotellist', 'hotelcode'}) or native > 99999999:
        return {'state': 'native_key_not_direct_anex'}
    try:
        if parts.port not in (None, 443):
            return {'state': 'native_link_port'}
    except ValueError:
        return {'state': 'native_link_port'}
    return {'state': 'verified_native_pair', 'anex_hotel_id': str(native),
            'operator_link_sha256': parsed['operator_link_sha256'],
            'operator_link_host': parsed['operator_link_host'], 'operator_id': 13,
            'tv_hotel_id': hotel, 'namespace': 'anex_online'}


class Client:
    http_maximum=400
    search_start_maximum=None
    def __init__(self, home, root, directory, operation):
        self.q = Quota(home); self.directory = directory; self.operation = operation
        self.reserved = 0; self.calls = 0; self.tariff = 0; self.unknown = 0
        self.counts = collections.Counter(); self.deadline = time.monotonic()+660
        self.http_maximum=400;self.search_start_maximum=None
        with self.q.locked() as (_, data, _):
            self.before = {k: data[k] for k in ('provider_day','physical_http_attempts','tariff_search_units')}
        php = 'require $argv[1];$a=defined("TOURVISOR_ANEX_JWT")?TOURVISOR_ANEX_JWT:getenv("TOURVISOR_ANEX_JWT");$b=defined("TOURVISOR_JWT")?TOURVISOR_JWT:getenv("TOURVISOR_JWT");if(!$a||$a===$b)exit(42);fwrite(STDOUT,(string)$a);'
        token = subprocess.run(['php','-d','display_errors=0','-r',php,str(root/'config.php')],capture_output=True,timeout=15)
        if token.returncode or token.stderr.strip() or not token.stdout.strip():
            raise ValueError('tourvisor_account_guard')
        self.token = token.stdout.decode().strip()
        self.open = urllib.request.build_opener(shape.NoRedirect())

    def call(self, action, path, params):
        if (self.reserved >= self.http_maximum or time.monotonic() > self.deadline
                or (action=='search_start' and self.search_start_maximum is not None and self.tariff>=self.search_start_maximum)):
            raise ValueError('finite_budget_stop')
        index = self.reserved+1
        self.q.reserve(self.operation,index,action); self.reserved=index
        save_json(self.directory/f'request-{index:04d}.json',{'action':action,'path':path,'params':params})
        pairs=[(k,str(z).lower() if isinstance(z,bool) else str(z)) for k,v in params.items() for z in (v if isinstance(v,list) else [v])]
        req=urllib.request.Request(BASE+path+('?' + urllib.parse.urlencode(pairs) if pairs else ''),headers={'Authorization':'Bearer '+self.token,'Accept':'application/json'})
        self.calls+=1; self.tariff+=int(action=='search_start'); self.counts[action]+=1
        try:
            try:
                response=self.open.open(req,timeout=60)
            except urllib.error.HTTPError as error:
                response=error
            with response:
                code=response.code; raw=response.read(BODY_LIMIT+1)
            raw_hash=save_bytes(self.directory/f'response-{index:04d}.bin',raw)
            save_json(self.directory/f'response-{index:04d}.json',{'http_status':code,'raw_sha256':raw_hash,'complete_body':len(raw)<=BODY_LIMIT})
        except Exception:
            self.unknown+=1
            self.q.complete(self.operation,index,'failed_unknown_no_replay')
            raise
        self.q.complete(self.operation,index,'response_received')
        if len(raw)>BODY_LIMIT:
            raise ValueError('response_size_bound')
        if code in (401,403,429):
            raise ValueError('provider_http_'+str(code))
        if code != 200 and not(action=='tour_detail' and code==404):
            raise ValueError('provider_http_'+str(code))
        if code == 404:
            return code,{}, {'response_number':index,'raw_sha256':raw_hash}
        try:
            value=json.loads(raw)
        except ValueError:
            raise ValueError('response_json_invalid')
        return code,value,{'response_number':index,'raw_sha256':raw_hash}


def acquire(client, directory, selected, sleep=time.sleep, scope=None):
    country,departure,adults=(scope[k] for k in ('country_id','departure_id','adults')) if scope else (4,1,2)
    planning=[{'tv_hotel_id':r['tv_hotel_id'],'country_id':country,'departure_id':departure,'adults':adults,
               'child_ages_signature':'','departure_date':r['future_context']['departure_date'],
               'nights':r['future_context']['nights'],'missing_operator_ids':[13]} for r in selected]
    if scope and scope.get('scope_kind')=='owner_date_variation':
        groups=[dict(scope['wire_context'],hotel_ids=[r['tv_hotel_id'] for r in selected])]
    elif scope and scope.get('context_grouping') == 'exact_date_nights':
        contexts=collections.defaultdict(list)
        for row in planning:contexts[(row['departure_date'],row['nights'])].append(row)
        groups=[group for key in sorted(contexts) for group in shape.chunks(contexts[key])]
    else:
        groups=shape.chunks(planning)
    edges=[]; batches=[]; binding=None; unbound_details=0
    save_json(directory/'acquisition-plan.json',{'groups':groups,'hotel_ids':[r['tv_hotel_id'] for r in selected]})
    stop=None
    try:
        for number,g in enumerate(groups,1):
            params={'departureId':departure,'countryId':country,'dateFrom':g['date_from'],'dateTo':g['date_to'],
                    'nightsFrom':g['nights_from'],'nightsTo':g['nights_to'],'adults':adults,'childs':[],
                    'currency':'RUB','onlyCharter':False,'operatorIds':[13],'hotelIds':g['hotel_ids']}
            _,start,_=client.call('search_start','/tours/search',params); start=shape.unwrap(start)
            sid=shape.num(start.get('searchId')) or shape.num(start.get('id')) if isinstance(start,dict) else None
            if sid is None:raise ValueError('search_id_missing')
            done=False
            for delay in (2,4,7):
                sleep(delay);_,status,_=client.call('search_status',f'/tours/search/{sid}/status',{'operatorStatus':False})
                if shape.ready(status):done=True;break
            _,result,_=client.call('search_results',f'/tours/search/{sid}',{'limit':10000})
            returned=set();sent=set(g['hotel_ids'])
            for hotel in shape.hotel_rows(result):
                hid=shape.num(hotel.get('id')) if isinstance(hotel,dict) else None
                if hid not in sent or hid in returned:continue
                tours=[t for t in hotel.get('tours',[]) if shape.ident(t,'operator')==13 and shape.num(t.get('id') or t.get('tourId'))]
                if not tours:continue
                returned.add(hid);tid=shape.num(tours[0].get('id') or tours[0].get('tourId'))
                code,detail,raw=client.call('tour_detail',f'/tours/{tid}',{'currency':'RUB'})
                detail=shape.unwrap(detail)
                edge=({'state':'detail_404'} if code==404 else native_pair(detail,hid))
                if isinstance(detail,dict):
                    edge['detail_fields']=[k for k in sorted(detail) if re.fullmatch(r'[A-Za-z_][A-Za-z_0-9]{0,63}',k) and not shape.SECRET.search(k)][:60]
                    edge['detail_hotel_id']=shape.ident(detail,'hotel');edge['detail_operator_id']=shape.ident(detail,'operator')
                    edge['operator_link_field']='missing' if 'operatorLink' not in detail else 'null' if detail['operatorLink'] is None else type(detail['operatorLink']).__name__
                edge.update(tv_hotel_id=hid,batch=number,detail_raw=raw,search_id_sha256=hashlib.sha256(str(sid).encode()).hexdigest(),tour_id_sha256=hashlib.sha256(str(tid).encode()).hexdigest())
                save_json(directory/f'edge-{hid}.json',edge);edges.append(edge)
                if edge['state']=='verified_native_pair' and binding is None:
                    binding={'state':'verified_live_operator13_anextour_link','operator_id':13,'tv_hotel_id':hid,'detail_raw':raw,'account':'TOURVISOR_ANEX_JWT'}
                    save_json(directory/'operator-account-binding.json',binding)
                if binding is None:
                    unbound_details+=1
                    if unbound_details>=5:raise ValueError('operator_binding_not_proven')
            batches.append({'batch':number,'sent':len(sent),'returned_anex_hotels':len(returned),'search_complete':done})
            save_json(directory/f'batch-{number:04d}.json',batches[-1])
    except Exception as error:
        allowed={'finite_budget_stop','provider_http_401','provider_http_403','provider_http_429','quota_exhausted','operator_binding_not_proven','search_id_missing','response_json_invalid','response_size_bound'}
        stop=str(error) if str(error) in allowed or re.fullmatch(r'provider_http_[1-5][0-9]{2}',str(error)) else type(error).__name__
    targets=collections.defaultdict(set)
    for edge in edges:
        if edge['state']=='verified_native_pair':targets[edge['anex_hotel_id']].add(edge['tv_hotel_id'])
    for edge in edges:
        if edge['state']=='verified_native_pair' and len(targets[edge['anex_hotel_id']])!=1:edge['state']='hold_multiple_targets'
    return {'state':'acquired_retained_native_read_only','acquisition_state':'completed' if stop is None else 'partial_no_replay',
        'stop_reason':stop,'selected_hotels':len(selected),'batches':batches,'edges':edges,
        'operator_account_binding':binding,'edge_states':dict(collections.Counter(x['state'] for x in edges)),
        'supplier_calls':client.calls,'physical_http_reservations':client.reserved,'tariff_search_units':client.tariff,
        'unknown_http_calls':client.unknown,'actions':dict(client.counts),'quota_before':client.before,
        'global_usage':'not_measured','database_writes':0,'mapping_writes':0,'new_accepted_mappings':0,'safe_to_write_now':False}


if __name__=='__main__':
    directory=Path(os.environ['MATCH_RETAINED_DIR']);root=Path.home()/'www/anytoour.ru'
    data=json.loads((directory/'acquisition-input.json').read_text())
    operation=os.environ['MATCH_RETAINED_OPERATION'];scope=data.get('continuation_scope')
    if scope is None:
        if operation!='int-andromeda-match-retained-acquire-20261020-v1' or data.get('parent_evidence_sha256')!=PARENT_EVIDENCE:raise SystemExit('acquisition_parent_binding')
        selected=select_rows(data['current'])
        if len(selected)!=105 or shape.plan_id_digest([r['tv_hotel_id'] for r in selected])!=SELECTED_IDS_SHA256:raise SystemExit('acquisition_cohort_binding')
    else:
        if (load_scope_file(Path(__file__).resolve().parents[2],data)!=scope or operation!=scope.get('operation_id')
                or data.get('parent_evidence_sha256')!=scope.get('parent_evidence_sha256')):raise SystemExit('continuation_scope_binding')
        selected=select_scope_rows(scope,data['current'])
        if scope.get('scope_kind')=='owner_date_variation':
            context_claim=reserve_contexts(Path.home(),scope,operation,data['prior_effective_requests'])
        else:
            if scope.get('scope_kind')=='never_selected_bounded':
                check_never_selected_requests(scope,data['prior_effective_requests'])
            selection_claim=reserve_selection(Path.home(),scope,operation)
    client=Client(Path.home(),root,directory,os.environ['MATCH_RETAINED_OPERATION'])
    configure_scope_budget(client,scope)
    result=acquire(client,directory,selected,scope=scope)
    result['parent_evidence_sha256']=scope['parent_evidence_sha256'] if scope else PARENT_EVIDENCE
    result['selected_id_sha256']=shape.plan_id_digest([r['tv_hotel_id'] for r in selected])
    if scope:result.update(continuation_scope_sha256=data['continuation_scope_sha256'],scope_id=scope['scope_id'])
    if 'continuation_catalogue_sha256' in data:
        result['continuation_catalogue_sha256']=data['continuation_catalogue_sha256']
    if scope and scope.get('scope_kind')=='owner_date_variation':
        if hashlib.sha256((Path.home()/'.anytoour-match/retained-selection-claims.json').read_bytes()).hexdigest()!=context_claim['hotel_ledger_sha256']:raise SystemExit('hotel_ledger_changed_unknown_no_replay')
        result.update(context_claim,context_origin=scope['context_origin'],wire_context=scope['wire_context'])
    if scope and scope.get('scope_kind')=='never_selected_bounded':
        if hashlib.sha256((Path.home()/'.anytoour-match/retained-selection-claims.json').read_bytes()).hexdigest()!=selection_claim:
            raise SystemExit('selection_ledger_changed_unknown_no_replay')
        result.update(selection_reservation_sha256=selection_claim,context_origin=scope['context_origin'],wire_context=scope['wire_context'])
    print(json.dumps(result,sort_keys=True,separators=(',',':')))
