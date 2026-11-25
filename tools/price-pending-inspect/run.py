"""One read-only SSH stdin inspection, never installs or invokes a publisher."""
import json,os,pathlib,re,subprocess
import admit
ROOT=pathlib.Path(__file__).parent
def main():
 os.umask(0o077);tmp=pathlib.Path(os.environ['RUNNER_TEMP']);out=tmp/'price-result';out.mkdir(exist_ok=False)
 admit.main()
 host=os.environ['DEPLOY_HOST'];user=os.environ['DEPLOY_USER'];assert re.fullmatch(r'[A-Za-z0-9_.:-]+',host) and not host.startswith('-') and re.fullmatch(r'[A-Za-z0-9_-]+',user)
 opts=['-i',str(tmp/'channel_key'),'-o','BatchMode=yes','-o','IdentitiesOnly=yes','-o','StrictHostKeyChecking=yes','-o','GlobalKnownHostsFile=/dev/null','-o','UserKnownHostsFile='+str(tmp/'channel_known_hosts'),'-o','ConnectTimeout=15','-o','ServerAliveInterval=30','-o','ServerAliveCountMax=3']
 command='unset GIT_DIR GIT_WORK_TREE GIT_COMMON_DIR GIT_INDEX_FILE GIT_CONFIG_PARAMETERS GIT_CONFIG_COUNT GIT_OBJECT_DIRECTORY GIT_ALTERNATE_OBJECT_DIRECTORIES; export GIT_OPTIONAL_LOCKS=0; php -d display_errors=0 -d log_errors=0'
 p=subprocess.run(['ssh',*opts,user+'@'+host,command],input=(ROOT/'inspect.php').read_bytes(),capture_output=True,timeout=180)
 (out/'original-pending.json').write_bytes(p.stdout);(out/'status.json').write_text(json.dumps({'returncode':p.returncode,'stderr_bytes':len(p.stderr)})+'\n')
 assert p.returncode==0,'read_failed_no_retry'
 r=json.loads(p.stdout);assert r['ok'] is True
 print('PENDING_ORIGINAL',json.dumps({k:r[k] for k in ('ok','pending','child_journal','pending_feed_files','runtime','server_writes','completed_hotels','target') if k in r},separators=(',',':')),flush=True)
if __name__=='__main__':main()
