#!/usr/bin/env python3
from __future__ import annotations
import argparse, base64, json, os, pathlib, re, shlex, subprocess, tarfile

OP='hotel-match-samo-registry-current-bridge-audit-1971-20260925-v39'
SHA_RE=re.compile(r'^[0-9a-f]{40}$')
HASH_RE=re.compile(r'^[0-9a-f]{64}$')
HOST_RE=re.compile(r'^[A-Za-z0-9][A-Za-z0-9.-]*$')
USER_RE=re.compile(r'^[A-Za-z0-9_][A-Za-z0-9_.-]{0,63}$')

def need(v: bool, why: str) -> None:
    if not v: raise ValueError(why)

def run(args:list[str], *, data:bytes|None=None, timeout:int=120, check:bool=True) -> subprocess.CompletedProcess:
    p=subprocess.run(args,input=data,stdout=subprocess.PIPE,stderr=subprocess.PIPE,timeout=timeout)
    if check and p.returncode:
        raise RuntimeError('command_failed:'+pathlib.Path(args[0]).name+':'+str(p.returncode))
    return p

def key_material(raw:str)->str:
    raw=raw.replace('\r','').strip()
    if 'PRIVATE KEY' not in raw:
        raw=base64.b64decode(raw,validate=True).decode()
    need('PRIVATE KEY' in raw,'ssh_key')
    return raw.rstrip()+'\n'

def main()->int:
    ap=argparse.ArgumentParser()
    ap.add_argument('--source-root',default='.')
    ap.add_argument('--input-dir',default='input')
    ap.add_argument('--terminal-dir',default='terminal')
    a=ap.parse_args()
    root=pathlib.Path(a.source_root).resolve(); inp=pathlib.Path(a.input_dir).resolve(); out=pathlib.Path(a.terminal_dir).resolve()
    out.mkdir(parents=True,exist_ok=True)
    op=os.environ.get('MATCH_V39_OPERATION',''); head=os.environ.get('MATCH_V39_SOURCE_SHA','')
    v38sha=os.environ.get('MATCH_V39_V38_RESULT_SHA',''); fsha=os.environ.get('MATCH_V39_FRONTIER_RESULT_SHA','')
    host=os.environ.get('MATCH_V39_HOST',''); user=os.environ.get('MATCH_V39_USER','')
    need(op==OP,'operation'); need(SHA_RE.fullmatch(head) is not None,'source_sha')
    need(HASH_RE.fullmatch(v38sha) is not None and HASH_RE.fullmatch(fsha) is not None,'input_hash')
    need(HOST_RE.fullmatch(host) is not None and USER_RE.fullmatch(user) is not None,'ssh_identity')
    files={
      'reservation.json':inp/'reservation.json',
      'v38-result.json':inp/'v38-result.json',
      'frontier-result.json':inp/'frontier-result.json',
      'hotel_match_samo_registry_current_bridge_audit_v39.php':root/'scripts/diagnostics/hotel_match_samo_registry_current_bridge_audit_v39.php',
    }
    for p in files.values(): need(p.is_file() and not p.is_symlink(),'input_file')
    import hashlib
    need(hashlib.sha256(files['v38-result.json'].read_bytes()).hexdigest()==v38sha,'v38_hash')
    need(hashlib.sha256(files['frontier-result.json'].read_bytes()).hexdigest()==fsha,'frontier_hash')
    res=json.loads(files['reservation.json'].read_text())
    need(res.get('operation')==OP and res.get('state')=='reserved_before_read_only_audit','reservation')
    work=out/'.transport'; work.mkdir(exist_ok=False)
    key=work/'key'; known=work/'known_hosts'
    key.write_text(key_material(os.environ.get('MATCH_V39_KEY',''))); key.chmod(0o600)
    run(['ssh-keygen','-y','-f',str(key)],timeout=10)
    scan=run(['ssh-keyscan','-T','15','-t','ed25519',host],timeout=20).stdout
    need(bool(scan),'ssh_hostkey'); known.write_bytes(scan); known.chmod(0o600)
    fps=run(['ssh-keygen','-lf',str(known),'-E','sha256'],timeout=10).stdout.decode().splitlines()
    need(len({x.split()[1] for x in fps if len(x.split())>1})==1,'ssh_fingerprint_count')
    opts=['-i',str(key),'-o','IdentitiesOnly=yes','-o','BatchMode=yes','-o','StrictHostKeyChecking=yes',
          '-o','UserKnownHostsFile='+str(known),'-o','GlobalKnownHostsFile=/dev/null','-o','ConnectTimeout=15',
          '-o','ServerAliveInterval=15','-o','ServerAliveCountMax=3','-o','LogLevel=ERROR']
    remote=user+'@'+host
    rdir=f'$HOME/.anytoour-match/operations/{OP}'
    mkdir=f'set -eu; umask 077; test ! -e "{rdir}"; mkdir -p "{rdir}/payload/scripts/diagnostics" "{rdir}/input"'
    run(['ssh',*opts,remote,mkdir],timeout=45)
    targets={
      'reservation.json':f'{rdir}/reservation.json',
      'v38-result.json':f'{rdir}/input/v38-result.json',
      'frontier-result.json':f'{rdir}/input/frontier-result.json',
      'hotel_match_samo_registry_current_bridge_audit_v39.php':f'{rdir}/payload/scripts/diagnostics/hotel_match_samo_registry_current_bridge_audit_v39.php',
    }
    for name,p in files.items():
        run(['scp',*opts,str(p),remote+':'+targets[name]],timeout=90)
    envs={
      'ANYTOUR_ROOT':'$HOME/www/anytoour.ru',
      'MATCH_OPERATION_DIR':f'$HOME/.anytoour-match/operations/{OP}',
      'MATCH_V38_RESULT':f'$HOME/.anytoour-match/operations/{OP}/input/v38-result.json',
      'MATCH_V38_RESULT_SHA':v38sha,
      'MATCH_FRONTIER_RESULT':f'$HOME/.anytoour-match/operations/{OP}/input/frontier-result.json',
      'MATCH_FRONTIER_RESULT_SHA':fsha,
      'MATCH_SOURCE_SHA':head,
    }
    prefix=' '.join(k+'='+shlex.quote(v) for k,v in envs.items())
    php=f'$HOME/.anytoour-match/operations/{OP}/payload/scripts/diagnostics/hotel_match_samo_registry_current_bridge_audit_v39.php'
    cmd=f'set -eu; cd "$HOME/www/anytoour.ru"; {prefix} php {shlex.quote(php)} --execute'
    execp=run(['ssh',*opts,remote,cmd],timeout=240,check=False)
    (out/'remote.stdout').write_bytes(execp.stdout); (out/'remote.stderr').write_bytes(execp.stderr)
    tarcmd=f'cd "{rdir}"; tar -czf - reservation.json result.json receipt.json'
    archive=run(['ssh',*opts,remote,tarcmd],timeout=60).stdout
    apath=out/'server.tgz'; apath.write_bytes(archive)
    with tarfile.open(apath,'r:gz') as tf:
        for name in ('reservation.json','result.json','receipt.json'):
            m=tf.getmember(name); need(m.isfile(),'terminal_member')
            b=tf.extractfile(m).read(); (out/name).write_bytes(b)
    need(execp.returncode==0,'remote_php_failed')
    print(json.dumps({'status':'complete','operation':OP,'server_writes':'operation_receipts_only','provider_calls':0,'database_writes':0},sort_keys=True))
    return 0

if __name__=='__main__':
    raise SystemExit(main())
