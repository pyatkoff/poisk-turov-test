#!/usr/bin/env python3
"""One-shot server-current reconciliation for observed ANEX/Andromeda hotel gaps."""
from __future__ import annotations
import argparse, json
from pathlib import Path
OPERATION_ID='hotel-observed-bulk-1759-20260911-v1'

def remote(request):
    from anex_search3_owner_decisions import ssh_php
    source=Path(__file__).with_suffix('.php').read_text(encoding='utf-8').removeprefix('<?php')
    return ssh_php(source,request,maximum_bytes=4000000)

def validate(value):
    if value.get('operation_id')!=OPERATION_ID: raise ValueError('wrong_operation')
    if value.get('status')=='completed':
        if value.get('supplier_calls')!=0 or value.get('readback_verified') is not True: raise ValueError('unverified_result')
        if value.get('accepted_total')!=value.get('database_writes'): raise ValueError('write_count_mismatch')
        if value['accepted_total']!=value['anex']['accepted']+value['andromeda']['accepted']: raise ValueError('provider_count_mismatch')
    return value

def main():
    p=argparse.ArgumentParser(description=__doc__);p.add_argument('phase',choices=('apply','receipt'));p.add_argument('--execute',action='store_true');p.add_argument('--output',type=Path);a=p.parse_args()
    if not a.execute:p.error('explicit --execute required')
    value=validate(remote({'operation_id':OPERATION_ID,'phase':a.phase}))
    if a.output:a.output.write_text(json.dumps(value,ensure_ascii=False,sort_keys=True,indent=2)+'\n')
    print(json.dumps(value,ensure_ascii=False,sort_keys=True))
    if value.get('status') not in ('completed','empty','not_started','reserved_or_unknown'):raise SystemExit(1)
if __name__=='__main__':main()
