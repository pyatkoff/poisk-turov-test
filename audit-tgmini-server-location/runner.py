"""One bounded inspection through existing targets; keep output encrypted."""
import base64
import hashlib
import json
import os
import re
import socket
import subprocess
import tempfile
from pathlib import Path
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import padding
from cryptography.hazmat.primitives.ciphers.aead import AESGCM

def addresses(host):
    try:
        return sorted({x[4][0] for x in socket.getaddrinfo(host, None)})
    except OSError:
        return []

def inspect(alias, work, dns):
    target = {k: os.environ.get(alias+'_'+k, '') for k in ('KEY','HOST','USER')}
    if not all(target.values()):
        return {'alias': alias, 'status': 'SETTINGS_UNAVAILABLE'}
    if not re.fullmatch(r'[A-Za-z0-9_.:-]+',target['HOST']) or target['HOST'].startswith('-') or not re.fullmatch(r'[A-Za-z0-9_-]+',target['USER']):
        return {'alias': alias, 'status': 'TARGET_INVALID'}
    raw = target['KEY'].replace('\r','')
    raw = raw.replace('\\n','\n') if 'PRIVATE KEY' in raw else base64.b64decode(raw).decode()
    key = work/(alias+'-key');key.write_text(raw.rstrip()+'\n');key.chmod(0o600)
    known = work/'known_hosts'
    source = Path('audit-tgmini-server-location/reader.py').read_bytes()
    if hashlib.sha256(source).hexdigest() != os.environ['READER_SHA256']:
        raise RuntimeError('reader_changed')
    cmd = ['ssh','-i',str(key),'-o','IdentitiesOnly=yes','-o','BatchMode=yes',
           '-o','StrictHostKeyChecking=accept-new','-o','UserKnownHostsFile='+str(known),
           '-o','GlobalKnownHostsFile=/dev/null','-o','ConnectTimeout=12',
           target['USER']+'@'+target['HOST'],'python3 -I -B -']
    p = subprocess.run(cmd,input=source,capture_output=True,timeout=55)
    host_ips = addresses(target['HOST'])
    identity = {'alias':alias,'target_matches_tg_dns':bool(set(host_ips)&set(dns['tg.rhat.ru'])),
                'target_matches_anytoour_dns':bool(set(host_ips)&set(dns['anytoour.ru']))}
    if p.returncode:
        return identity | {'status':'SSH_READ_FAILED'}
    if len(p.stdout)>100000:
        raise RuntimeError('report_size_bound')
    r=json.loads(p.stdout)
    if r.get('kind')!='tgmini_anytour_location' or any(r.get(k) is not False for k in ('server_writes','code_executed','database_reads','customer_log_reads')):
        raise RuntimeError('report_scope')
    fingerprints=[]
    for line in known.read_text().splitlines():
        f=line.split()
        if len(f)==3:
            fingerprints.append({'algorithm':f[1],'sha256':'SHA256:'+base64.b64encode(hashlib.sha256(base64.b64decode(f[2])).digest()).decode().rstrip('=')})
    return identity | {'status':'READ_COMPLETE','first_connection_trust':True,'observed_host_keys':fingerprints,'metadata':r}

def main():
    os.umask(0o077)
    dns = {name:addresses(name) for name in ['tg.rhat.ru','anytoour.ru','anytour.online']}
    with tempfile.TemporaryDirectory(prefix='tgmini-location-',dir=os.environ['RUNNER_TEMP']) as temp:
        results=[]
        for alias in ['CURRENT','LEGACY']:
            try: results.append(inspect(alias,Path(temp),dns))
            except Exception as e: results.append({'alias':alias,'status':'READ_FAILED','error_type':type(e).__name__})
        report={'kind':'tgmini_location_report','dns':dns,'targets':results}
        public=serialization.load_pem_public_key(base64.b64decode(os.environ['REPORT_PUBLIC_KEY_B64']))
        key,nonce=AESGCM.generate_key(bit_length=256),os.urandom(12)
        aad=('tgmini-anytour-location-'+os.environ['GITHUB_SHA']).encode()
        wrapped=public.encrypt(key,padding.OAEP(mgf=padding.MGF1(hashes.SHA256()),algorithm=hashes.SHA256(),label=None))
        ciphertext=AESGCM(key).encrypt(nonce,json.dumps(report).encode(),aad)
        b64=lambda value:base64.b64encode(value).decode()
        envelope={'schema':1,'workflow_sha':os.environ['GITHUB_SHA'],'aad':b64(aad),'wrapped_key':b64(wrapped),'nonce':b64(nonce),'ciphertext':b64(ciphertext)}
        out=Path('encrypted-location');out.mkdir(exist_ok=True)
        (out/'location.enc.json').write_text(json.dumps(envelope))
        for r in results: print(r['alias']+'='+r['status'])
        print('ENCRYPTED_LOCATION_REPORT_READY')

if __name__=='__main__':main()
