#!/usr/bin/env python3
"""Reproduce and promote a pinned full-country pending delta; never replay country imports."""
from __future__ import annotations
import argparse
import hashlib
import json
from pathlib import Path
import re
import subprocess
from zipfile import ZipFile
from anex_tourvisor_link_import import save, digest

OPERATION_ID = 'andromeda-1759-country6-pending-20260910-v1'
ARCHIVE_SHA = '7f2fc5c5f83ddda924d546b003ba03c9dad738ed95d54ff0a53d7b14337d6b8f'
REQUEST_SHA = '9e89e6585b01205ac9a0b1bc47f91c99585eb8cc8863fc89e3b463c3b3d9d687'
COUNTRIES = ('uae','thailand','vietnam','sri-lanka','maldives','cuba')


def bundle(path: Path):
    if hashlib.sha256(path.read_bytes()).hexdigest() != ARCHIVE_SHA:
        raise ValueError('unreviewed_complete_archive')
    result = {}
    with ZipFile(path) as archive:
        for country in COUNTRIES:
            parts = {p: json.loads(archive.read(f'{country}-{p}.json')) for p in ('catalog','local','plan')}
            for part, value in parts.items():
                if (value.get('status') != 'saved_complete_part' or value.get('country') != country
                        or value.get('part') != part or digest(value['data']) != value['data_sha256']):
                    raise ValueError('saved_part_unconfirmed')
            meta = parts['catalog']
            capture = {k: meta[k] for k in ('country_id','country_name','supplier_country_id')}
            capture.update(catalog=parts['catalog']['data'], local=parts['local']['data'])
            plan = parts['plan']['data']
            if digest(capture) != meta['capture_sha256'] or digest(plan) != meta['plan_sha256']:
                raise ValueError('saved_capture_unconfirmed')
            result[country] = {'capture': capture, 'plan': plan}
    return result


def request(path: Path, fixture: Path | None = None):
    data = bundle(path)
    directory = Path(__file__).resolve().parent
    # The same PHP policy runs offline here and under current DB locks on the server.
    program = "define('CE_LIBRARY_ONLY',true);require " + json.dumps(str(directory/'andromeda_country_expansion.php')) + ';require ' + json.dumps(str(directory/'andromeda_country_pending.php')) + ";echo ce_json(ce_pending_request(json_decode(file_get_contents('php://stdin'),true,64,JSON_THROW_ON_ERROR)));"
    proc = subprocess.run(['php','-r',program],input=json.dumps(data,ensure_ascii=False),text=True,capture_output=True,timeout=45)
    if proc.returncode != 0:
        raise ValueError('offline_policy_failed')
    payload = json.loads(proc.stdout)
    if digest(payload) != REQUEST_SHA or payload.get('count') != 403 or payload.get('operation_id') != OPERATION_ID:
        raise ValueError('reviewed_pending_delta_changed')
    if fixture is not None:
        save(fixture, data, exclusive=True)
    return payload


def validate_result(result, payload):
    if (result.get('status') != 'accepted' or result.get('operation_id') != OPERATION_ID
            or result.get('request_sha256') != REQUEST_SHA or result.get('updated') != payload['count']
            or result.get('readback_verified') is not True or result.get('other_identities_unchanged') is not True
            or result.get('supplier_calls') != 0):
        raise ValueError('pending_acceptance_unconfirmed')
    expected = {r['external_hotel_id']:(r['local_hotel_id'],c['country_id']) for c in payload['countries'].values() for r in c['rows']}
    rows = result.get('rows',[])
    actual = {str(r['external_hotel_id']):(r['local_hotel_id'],r['country_id']) for r in rows}
    if len(rows) != len(expected) or actual != expected:
        raise ValueError('pending_pair_readback_mismatch')
    if any(r.get('decision_status')!='accepted' or not re.fullmatch('[0-9a-f]{64}',r.get('evidence_sha256','')) for r in rows):
        raise ValueError('pending_evidence_readback_mismatch')


def apply(payload, receipt: Path, transport=None):
    if digest(payload) != REQUEST_SHA:
        raise ValueError('unreviewed_request')
    if receipt.is_symlink():
        raise ValueError('receipt_symlink')
    if receipt.exists():
        old=json.loads(receipt.read_bytes())
        if old.get('state')!='finalized' or old.get('request_sha256')!=REQUEST_SHA or old.get('result_sha256')!=digest(old.get('result')):
            raise ValueError('reserved_unknown_do_not_replay')
        validate_result(old['result'],payload)
        return {'status':'already_finalized','new_writes':0,'saved_result':old['result']}
    save(receipt,{'state':'reserved','operation_id':OPERATION_ID,'request_sha256':REQUEST_SHA},exclusive=True)
    if transport is None:
        from anex_search3_owner_decisions import ssh_php
        directory=Path(__file__).resolve().parent; root=directory.parents[1]
        source=(root/'app/integrations/anex-search-mapping-registry.php').read_text().removeprefix('<?php')
        source+="\ndefine('CE_LIBRARY_ONLY',true);\n"+(directory/'andromeda_country_expansion.php').read_text().removeprefix('<?php')
        source+='\n'+(directory/'andromeda_country_pending.php').read_text().removeprefix('<?php')
        source+='''\nerror_reporting(0);ob_start();try{$q=json_decode(file_get_contents('php://stdin'),true,64,JSON_THROW_ON_ERROR);$v=ce_pending_execute($q);}catch(Throwable $e){$v=['status'=>'failed','retry'=>false,'reason'=>in_array($e->getMessage(),['pending_manifest','prior_pending_operation_do_not_replay','transaction_rolled_back','committed_readback_unconfirmed'],true)?$e->getMessage():'runtime_failure'];}while(ob_get_level())ob_end_clean();echo ce_json($v);'''
        result=ssh_php(source,payload,maximum_bytes=4000000)
    else:
        result=transport(payload)
    save(receipt.with_name(receipt.name+'.outcome.json'),result,exclusive=True)
    validate_result(result,payload)
    save(receipt,{'state':'finalized','operation_id':OPERATION_ID,'request_sha256':REQUEST_SHA,'result_sha256':digest(result),'result':result})
    return result


def main():
    p=argparse.ArgumentParser(description=__doc__)
    p.add_argument('--archive',required=True,type=Path);p.add_argument('--output',required=True,type=Path)
    p.add_argument('--fixture',type=Path);p.add_argument('--apply',action='store_true');p.add_argument('--receipt',type=Path)
    args=p.parse_args()
    if args.apply and args.receipt is None:p.error('--apply requires a durable receipt')
    payload=request(args.archive,args.fixture)
    result=apply(payload,args.receipt) if args.apply else {'status':'prepared_not_applied','request':payload}
    save(args.output,result,exclusive=True)
    print(json.dumps(result if args.apply else {'status':result['status'],'count':payload['count'],'request_sha256':REQUEST_SHA},ensure_ascii=False))

if __name__=='__main__':main()
