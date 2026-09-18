#!/usr/bin/env python3
import importlib.util, io, os, pathlib, subprocess, sys

HERE=pathlib.Path(__file__).resolve().parent
SPEC=importlib.util.spec_from_file_location('mauritius27_direct_v2', HERE/'hotel_match_anex_user_seen_mauritius27_direct_v2.py')
if SPEC is None or SPEC.loader is None: raise RuntimeError('module_load')
M=importlib.util.module_from_spec(SPEC); SPEC.loader.exec_module(M)

if __name__=='__main__':
    if '--self-test' in sys.argv:
        sys.exit(M.main())
    if '--execute' in sys.argv:
        root=os.environ.get('ANYTOUR_ROOT','').rstrip('/')
        if not root: raise RuntimeError('root_missing')
        code='require $argv[1]; $v=defined("TOURVISOR_JWT") ? TOURVISOR_JWT : getenv("TOURVISOR_JWT"); fwrite(STDOUT,(string)$v);'
        cp=subprocess.run(['php','-r',code,'--',root+'/config.php'],capture_output=True,text=True,check=True)
        token=cp.stdout.strip()
        if not token: raise RuntimeError('token_missing')
        sys.stdin=io.StringIO(token)
    sys.exit(M.main())
