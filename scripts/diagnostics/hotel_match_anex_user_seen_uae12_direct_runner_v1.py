#!/usr/bin/env python3
import importlib.util, json, os, pathlib, sys

HERE=pathlib.Path(__file__).resolve().parent
SPEC=importlib.util.spec_from_file_location('uae12_direct_v1', HERE/'hotel_match_anex_user_seen_uae12_direct_v1.py')
if SPEC is None or SPEC.loader is None: raise RuntimeError('module_load')
M=importlib.util.module_from_spec(SPEC); SPEC.loader.exec_module(M)

def write_locked_compat(f,obj):
    text=json.dumps(obj,ensure_ascii=False,sort_keys=True)+'\n'
    f.seek(0); f.truncate(0)
    if 'b' in getattr(f,'mode',''):
        raw=text.encode(); n=f.write(raw); expected=len(raw)
    else:
        n=f.write(text); expected=len(text)
    if n!=expected: raise RuntimeError('locked_write_short')
    f.flush(); os.fsync(f.fileno())

M.write_locked=write_locked_compat
if __name__=='__main__': sys.exit(M.main())
