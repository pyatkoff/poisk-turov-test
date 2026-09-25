#!/usr/bin/env python3
from __future__ import annotations
import re
import hotel_match_v39_readonly_runner as runner

_orig_quote=runner.shlex.quote
_home=re.compile(r'^\$HOME/[A-Za-z0-9_./-]+$')

def _remote_quote(value:str)->str:
    if _home.fullmatch(value):
        return '"' + value + '"'
    return _orig_quote(value)

runner.shlex.quote=_remote_quote

if __name__=='__main__':
    raise SystemExit(runner.main())
