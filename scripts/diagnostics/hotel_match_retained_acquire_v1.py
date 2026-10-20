"""Finite missing-ANEX acquisition; raw HTTP precedes parsing, no retries/writes."""
from __future__ import annotations
import collections
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
    def __init__(self, home, root, directory, operation):
        self.q = Quota(home); self.directory = directory; self.operation = operation
        self.reserved = 0; self.calls = 0; self.tariff = 0; self.unknown = 0
        self.counts = collections.Counter(); self.deadline = time.monotonic()+660
        with self.q.locked() as (_, data, _):
            self.before = {k: data[k] for k in ('provider_day','physical_http_attempts','tariff_search_units')}
        php = 'require $argv[1];$a=defined("TOURVISOR_ANEX_JWT")?TOURVISOR_ANEX_JWT:getenv("TOURVISOR_ANEX_JWT");$b=defined("TOURVISOR_JWT")?TOURVISOR_JWT:getenv("TOURVISOR_JWT");if(!$a||$a===$b)exit(42);fwrite(STDOUT,(string)$a);'
        token = subprocess.run(['php','-d','display_errors=0','-r',php,str(root/'config.php')],capture_output=True,timeout=15)
        if token.returncode or token.stderr.strip() or not token.stdout.strip():
            raise ValueError('tourvisor_account_guard')
        self.token = token.stdout.decode().strip()
        self.open = urllib.request.build_opener(shape.NoRedirect())

    def call(self, action, path, params):
        if self.reserved >= 400 or time.monotonic() > self.deadline:
            raise ValueError('finite_budget_stop')
        index = self.reserved+1
        self.q.reserve(self.operation,index,action); self.reserved=index
        save_json(self.directory/f'request-{index:04d}.json',{'action':action,'path':path,'params':params})
        pairs=[(k,str(z).lower() if isinstance(z,bool) else str(z)) for k,v in params.items() for z in (v if isinstance(v,list) else [v])]
        req=urllib.request.Request(BASE+path+('?' + urllib.parse.urlencode(pairs) if pairs else ''),headers={'Authorization':'Bearer '+self.token,'Accept':'application/json'})
        self.calls+=1; self.tariff+=int(action=='search_start'); self.counts[action]+=1
        try:
            try:
                response=self.open.open(req,timeout=30)
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


def acquire(client, directory, selected, sleep=time.sleep):
    planning=[{'tv_hotel_id':r['tv_hotel_id'],'country_id':4,'departure_id':1,'adults':2,
               'child_ages_signature':'','departure_date':r['future_context']['departure_date'],
               'nights':r['future_context']['nights'],'missing_operator_ids':[13]} for r in selected]
    groups=shape.chunks(planning); edges=[]; batches=[]; binding=None; unbound_details=0
    save_json(directory/'acquisition-plan.json',{'groups':groups,'hotel_ids':[r['tv_hotel_id'] for r in selected]})
    stop=None
    try:
        for number,g in enumerate(groups,1):
            params={'departureId':1,'countryId':4,'dateFrom':g['date_from'],'dateTo':g['date_to'],
                    'nightsFrom':g['nights_from'],'nightsTo':g['nights_to'],'adults':2,'childs':[],
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
    if data.get('parent_evidence_sha256')!=PARENT_EVIDENCE:raise SystemExit('acquisition_parent_binding')
    selected=select_rows(data['current'])
    if len(selected)!=105 or shape.plan_id_digest([r['tv_hotel_id'] for r in selected])!=SELECTED_IDS_SHA256:raise SystemExit('acquisition_cohort_binding')
    client=Client(Path.home(),root,directory,os.environ['MATCH_RETAINED_OPERATION'])
    result=acquire(client,directory,selected)
    result['parent_evidence_sha256']=PARENT_EVIDENCE
    result['selected_id_sha256']=SELECTED_IDS_SHA256
    print(json.dumps(result,sort_keys=True,separators=(',',':')))
