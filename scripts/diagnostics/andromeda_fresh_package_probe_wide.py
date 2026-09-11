#!/usr/bin/env python3
"""Use the checked fresh-package probe with a wider sellable ANEX search window."""
import json
import os
from pathlib import Path
import sys

sys.path.insert(0, str(Path(__file__).resolve().parent))
import andromeda_fresh_package_probe as base

OLD = """// Previously proven broad parity scenario: Moscow -> Turkey, 2026-10-05, 7n, 2 adults, AI, ANEX only.\n    $params=['TOWNFROMINC'=>1,'STATEINC'=>3,'CHECKIN_BEG'=>'20261005','CHECKIN_END'=>'20261005',\n        'NIGHTS_FROM'=>7,'NIGHTS_TILL'=>7,'ADULT'=>2,'CHILD'=>0,'CURRENCYINC'=>643,\n        'MEAL'=>'5','OPERATORS'=>'5','PACKETTYPE'=>0,'PAGE'=>1];"""
NEW = """// Wider read-only discovery: Moscow -> Turkey, 2026-09-20..27, 7-8n, 2 adults, ANEX only.\n    $params=['TOWNFROMINC'=>1,'STATEINC'=>3,'CHECKIN_BEG'=>'20260920','CHECKIN_END'=>'20260927',\n        'NIGHTS_FROM'=>7,'NIGHTS_TILL'=>8,'ADULT'=>2,'CHILD'=>0,'CURRENCYINC'=>643,\n        'OPERATORS'=>'5','PACKETTYPE'=>0,'PAGE'=>1];"""
if base.PHP.count(OLD) != 1:
    raise RuntimeError('wide_probe_source_mismatch')
WIDE_PHP = base.PHP.replace(OLD, NEW)


def execute(output_directory: Path, runner):
    def wide_runner(_source, request, maximum_bytes=0):
        return runner(WIDE_PHP, request, maximum_bytes=maximum_bytes)
    return base.execute(output_directory, wide_runner)


def main():
    if len(sys.argv) != 2 or sys.argv[1] != '--execute':
        raise SystemExit('usage: andromeda_fresh_package_probe_wide.py --execute')
    from anex_search3_owner_decisions import ssh_php
    out = Path(os.environ['RUNNER_TEMP'])/'andromeda-fresh-package'
    safe = execute(out, ssh_php)
    print(json.dumps({k:safe.get(k) for k in ('status','phase','supplier_calls','transport_error','requires_external_flights','buyer_price')}, ensure_ascii=False, sort_keys=True))
    if safe['status'] != 'captured':
        raise SystemExit('fresh wide package outcome not captured; no automatic retry')


if __name__ == '__main__':
    main()
