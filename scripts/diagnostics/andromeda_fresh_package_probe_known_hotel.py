#!/usr/bin/env python3
"""Probe one historically verified ANEX hotel scope to obtain a complete fresh package claim."""
import json
import os
from pathlib import Path
import sys

sys.path.insert(0, str(Path(__file__).resolve().parent))
import andromeda_fresh_package_probe as base
import andromeda_fresh_package_probe_wide as wide
import andromeda_fresh_package_supplier_error_probe as supplier

KNOWN = """// Known sellable scope from prior live acceptance: Moscow -> Egypt, Life Coral Hills, 2026-09-18, 8n, 2 adults, AI, ANEX only.\n    $params=['TOWNFROMINC'=>1,'STATEINC'=>3,'CHECKIN_BEG'=>'20260918','CHECKIN_END'=>'20260918',\n        'NIGHTS_FROM'=>8,'NIGHTS_TILL'=>8,'ADULT'=>2,'CHILD'=>0,'CURRENCYINC'=>643,\n        'MEAL'=>'5','OPERATORS'=>'5','HOTELS'=>'416247','PACKETTYPE'=>0,'PAGE'=>1];"""
if supplier.SUPPLIER_ERROR_PHP.count(wide.NEW) != 1:
    raise RuntimeError('known_hotel_probe_source_mismatch')
KNOWN_HOTEL_PHP = supplier.SUPPLIER_ERROR_PHP.replace(wide.NEW, KNOWN)


def execute(output_directory: Path, runner):
    def known_runner(_source, request, maximum_bytes=0):
        return runner(KNOWN_HOTEL_PHP, request, maximum_bytes=maximum_bytes)
    return base.execute(output_directory, known_runner)


def main():
    if len(sys.argv) != 2 or sys.argv[1] != '--execute':
        raise SystemExit('usage: andromeda_fresh_package_probe_known_hotel.py --execute')
    from anex_search3_owner_decisions import ssh_php
    out = Path(os.environ['RUNNER_TEMP'])/'andromeda-fresh-package'
    safe = execute(out, ssh_php)
    print(json.dumps({k:safe.get(k) for k in ('status','phase','supplier_calls','transport_error','supplier_error','requires_external_flights','buyer_price')}, ensure_ascii=False, sort_keys=True))
    if safe['status'] != 'captured':
        raise SystemExit('known-hotel package not captured; evidence saved; no automatic retry')


if __name__ == '__main__':
    main()
