#!/usr/bin/env python3
from __future__ import annotations
import re
import hotel_match_v39_readonly_runner as runner

runner.OP='hotel-match-samo-registry-current-bridge-audit-1971-20260925-v39r2'

_orig_quote=runner.shlex.quote
_home=re.compile(r'^\$HOME/[A-Za-z0-9_./-]+$')

def _remote_quote(value:str)->str:
    if _home.fullmatch(value):
        return '"' + value + '"'
    return _orig_quote(value)

_orig_run=runner.run

def _transport_run(args:list[str], **kwargs):
    if args and args[0]=='scp':
        fixed=[]
        for value in args:
            if ':$HOME/' in value:
                value=value.replace(':$HOME/',':',1)
            fixed.append(value)
        args=fixed
    return _orig_run(args,**kwargs)

runner.shlex.quote=_remote_quote
runner.run=_transport_run

if __name__=='__main__':
    raise SystemExit(runner.main())
