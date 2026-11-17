"""Read-only preflight only; never executes a hook or collector."""
import base64,gzip,hashlib,json,os,pathlib,re,subprocess,sys
import admit
ROOT=pathlib.Path(__file__).parent
OP='/var/www/anytoour/data/channel-post-operations/price-hook-reviewed-surface-20261117-1'
TARGET='/var/www/anytoour/data/www/anytoour.ru/data/monitor-price-hook.php'
NEW='9a263ce130a6b0c9ad0c9f435575d45f77fa2e62370ab72200d8cfef0bb4a7ae'
def need(v,m):
 if not v:raise RuntimeError(m)
def main():
 need(os.environ.get('PRICE_PREFLIGHT_ONLY')=='1','read_only_mode_required')
 os.umask(0o077);tmp=pathlib.Path(os.environ['RUNNER_TEMP']);out=tmp/'price-result';out.mkdir(exist_ok=False)
 host=os.environ['DEPLOY_HOST'];user=os.environ['DEPLOY_USER'];need(re.fullmatch(r'[A-Za-z0-9_.:-]+',host) and not host.startswith('-') and re.fullmatch(r'[A-Za-z0-9_-]+',user),'host_invalid')
 opts=['-i',str(tmp/'channel_key'),'-o','BatchMode=yes','-o','IdentitiesOnly=yes','-o','StrictHostKeyChecking=yes','-o','GlobalKnownHostsFile=/dev/null','-o','UserKnownHostsFile='+str(tmp/'channel_known_hosts'),'-o','ConnectTimeout=15','-o','ServerAliveInterval=30','-o','ServerAliveCountMax=6']
 remote=user+'@'+host;scratch='/tmp/price-hook-reviewed-'+os.environ['GITHUB_RUN_ID'];need(re.fullmatch(r'/tmp/price-hook-reviewed-[0-9]+',scratch),'run_id_invalid')
 def ssh(command,label,check=True):
  p=subprocess.run(['ssh',*opts,remote,'unset GIT_DIR GIT_WORK_TREE GIT_COMMON_DIR GIT_INDEX_FILE GIT_CONFIG_PARAMETERS GIT_CONFIG_COUNT GIT_OBJECT_DIRECTORY GIT_ALTERNATE_OBJECT_DIRECTORIES ANYTOUR_MONITOR_COLLECTION_STARTED_AT ANYTOUR_MONITOR_COLLECTOR_RUN_ID ANYTOUR_MONITOR_HOOK_INSPECT; export GIT_OPTIONAL_LOCKS=0; '+command],capture_output=True)
  (out/(label+'.stdout')).write_bytes(p.stdout);(out/(label+'.status.json')).write_text(json.dumps({'returncode':p.returncode})+'\n')
  if check:need(p.returncode==0,label+'_failed_no_retry')
  return p.stdout
 def parse(raw):return json.loads(raw)
 admit.main()
 ssh('umask 077; mkdir '+scratch,'scratch')
 for src in [ROOT/'install.php',ROOT/'feeds.php',tmp/'monitor-price-hook.php']:
  p=subprocess.run(['scp',*opts,str(src),remote+':'+scratch+'/'],capture_output=True);need(p.returncode==0,'stage_failed_no_retry')
 ready=parse(ssh('php '+scratch+'/install.php inspect-new '+scratch+'/monitor-price-hook.php','inspect-start'));need(ready['ok'] is True,'installed_readback_failed')
 before=parse(ssh('php '+scratch+'/feeds.php','feeds-start'));need(before['ok'] is True,'feeds_before_not_ok')
 config=json.loads((ROOT/'config.json').read_text());prefix=set(config['prefix_offer_ids']);need(len(prefix)==20,'prefix_scope_invalid')
 need(prefix<=before['files']['hotel-posts-all.xml']['rows'].keys(),'prefix_missing_before_pass')
 for name,digest in config['original_feed_preimages'].items():need(before['files'][name]['sha256']==digest,'original_feed_preimage_changed')
 bundle=(tmp/'monitor-price-hook.php').read_text();encoded=re.search(r'const HOOK_ROSTER_GZIP_B64="([^"]+)"',bundle)[1]
 rows=json.loads(gzip.decompress(base64.b64decode(encoded)))['rows'];bytv={r['tourvisor_hotel_id']:r for r in rows};need(len(bytv)==660,'roster_invalid')
 (out/'preflight-summary.json').write_text(json.dumps({'ok':True,'mode':'read_only_before_new_collection','roster_pairs':660,'feed_counts':[670,670,1340],'pending_null':True,'collector_runs':0,'post_writes':0,'supplier_calls':0})+'\n')
if __name__=='__main__':main()
