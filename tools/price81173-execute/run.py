"""Exact single-use 81173 repair, followed by separately verified code-only install."""
import json,os,pathlib,re,subprocess
import admit
ROOT=pathlib.Path(__file__).parent
def need(v,m):
 if not v:raise RuntimeError(m)
def main():
 os.umask(0o077);tmp=pathlib.Path(os.environ['RUNNER_TEMP']);out=tmp/'price-result';out.mkdir(exist_ok=False);admit.main()
 host=os.environ['DEPLOY_HOST'];user=os.environ['DEPLOY_USER'];need(re.fullmatch(r'[A-Za-z0-9_.:-]+',host) and not host.startswith('-') and re.fullmatch(r'[A-Za-z0-9_-]+',user),'host_invalid')
 opts=['-i',str(tmp/'channel_key'),'-o','BatchMode=yes','-o','IdentitiesOnly=yes','-o','StrictHostKeyChecking=yes','-o','GlobalKnownHostsFile=/dev/null','-o','UserKnownHostsFile='+str(tmp/'channel_known_hosts'),'-o','ConnectTimeout=15','-o','ServerAliveInterval=30','-o','ServerAliveCountMax=6']
 remote=user+'@'+host;scratch='/tmp/price81173-execute-'+os.environ['GITHUB_RUN_ID'];need(re.fullmatch(r'/tmp/price81173-execute-[0-9]+',scratch),'run_id_invalid')
 def ssh(command,label):
  p=subprocess.run(['ssh',*opts,remote,'unset GIT_DIR GIT_WORK_TREE GIT_COMMON_DIR GIT_INDEX_FILE GIT_CONFIG_PARAMETERS GIT_CONFIG_COUNT GIT_OBJECT_DIRECTORY GIT_ALTERNATE_OBJECT_DIRECTORIES ANYTOUR_MONITOR_COLLECTION_STARTED_AT ANYTOUR_MONITOR_COLLECTOR_RUN_ID ANYTOUR_MONITOR_HOOK_INSPECT; export GIT_OPTIONAL_LOCKS=0; '+command],capture_output=True,timeout=480)
  (out/(label+'.stdout')).write_bytes(p.stdout);(out/(label+'.status.json')).write_text(json.dumps({'returncode':p.returncode,'stderr_bytes':len(p.stderr)})+'\n');need(p.returncode==0,label+'_failed_read_original_receipts_no_retry');return p.stdout
 ssh('umask 077; mkdir '+scratch,'scratch')
 for src in [ROOT/'repair.php',ROOT/'install.php',tmp/'monitor-price-hook.php']:
  p=subprocess.run(['scp',*opts,str(src),remote+':'+scratch+'/'],capture_output=True,timeout=60);need(p.returncode==0,'stage_failed')
 r=json.loads(ssh('PRICE81173_APPLY=price81173-telegram429-original-completion-20261118-1 php '+scratch+'/repair.php','repair'))
 need(r['ok'] is True and r['cycle_closed'] is True and r['pending_null'] is True and r['max_writes']==0 and r['telegram_edits']==1,'repair_not_verified_stop')
 raw=ssh('php '+scratch+'/install.php inspect-old '+scratch+'/monitor-price-hook.php','install-readiness');ready=json.loads(raw);need(ready['ok'] is True,'install_readiness_failed')
 local=out/'install-readiness.json';local.write_bytes(raw)
 p=subprocess.run(['scp',*opts,str(local),remote+':'+scratch+'/install-readiness.json'],capture_output=True,timeout=60);need(p.returncode==0,'readiness_stage_failed')
 installed=json.loads(ssh('php '+scratch+'/install.php install '+scratch+'/monitor-price-hook.php '+scratch+'/install-readiness.json','install'));need(installed['ok'] is True,'install_failed')
 final=json.loads(ssh('php '+scratch+'/install.php inspect-new '+scratch+'/monitor-price-hook.php','final-readback'));need(final['ok'] is True,'final_readback_failed')
 (out/'summary.json').write_text(json.dumps({'ok':True,'repair':r,'install':installed,'final':final})+'\n')
 print(json.dumps({'ok':True,'pairs_completed':1,'telegram_edits':1,'max_writes':0,'cycle_closed':True,'installed_sha256':installed['installed_sha256']}))
if __name__=='__main__':main()
