#!/usr/bin/env python3
import importlib.util, io, json, os, pathlib, subprocess, sys

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

if __name__=='__main__':
    if '--execute' in sys.argv:
        root=os.environ.get('ANYTOUR_ROOT','').rstrip('/')
        if not root: raise RuntimeError('root_missing')
        code='require $argv[1]; $v=defined("TOURVISOR_JWT") ? TOURVISOR_JWT : getenv("TOURVISOR_JWT"); fwrite(STDOUT,(string)$v);'
        cp=subprocess.run(['php','-r',code,'--',root+'/config.php'],capture_output=True,text=True,check=True)
        token=cp.stdout.strip()
        if not token: raise RuntimeError('token_missing')
        sys.stdin=io.StringIO(token)
    sys.exit(M.main())
