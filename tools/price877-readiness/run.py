"""No retries, replay, provider collection or synthetic nightly identity."""
import base64,gzip,hashlib,json,os,pathlib,re,subprocess,sys,time
import admit
ROOT=pathlib.Path(__file__).parent
def need(v,m):
 if not v:raise RuntimeError(m)
def main():
 os.umask(0o077);tmp=pathlib.Path(os.environ['RUNNER_TEMP']);out=tmp/'price-result';out.mkdir(exist_ok=False)
 host=os.environ['DEPLOY_HOST'];user=os.environ['DEPLOY_USER'];need(re.fullmatch(r'[A-Za-z0-9_.:-]+',host) and not host.startswith('-') and re.fullmatch(r'[A-Za-z0-9_-]+',user),'host_invalid')
 opts=['-i',str(tmp/'channel_key'),'-o','BatchMode=yes','-o','IdentitiesOnly=yes','-o','StrictHostKeyChecking=yes','-o','GlobalKnownHostsFile=/dev/null','-o','UserKnownHostsFile='+str(tmp/'channel_known_hosts'),'-o','ConnectTimeout=15','-o','ServerAliveInterval=30','-o','ServerAliveCountMax=6']
 remote=user+'@'+host;scratch='/tmp/price877-readiness-'+os.environ['GITHUB_RUN_ID'];need(re.fullmatch(r'/tmp/price877-readiness-[0-9]+',scratch),'run_id_invalid')
 def ssh(command,label,check=True):
  p=subprocess.run(['ssh',*opts,remote,'unset GIT_DIR GIT_WORK_TREE GIT_COMMON_DIR GIT_INDEX_FILE GIT_CONFIG_PARAMETERS GIT_CONFIG_COUNT GIT_OBJECT_DIRECTORY GIT_ALTERNATE_OBJECT_DIRECTORIES ANYTOUR_MONITOR_COLLECTION_STARTED_AT ANYTOUR_MONITOR_COLLECTOR_RUN_ID ANYTOUR_MONITOR_HOOK_INSPECT; export GIT_OPTIONAL_LOCKS=0; '+command],capture_output=True)
  (out/(label+'.stdout')).write_bytes(p.stdout);(out/(label+'.status.json')).write_text(json.dumps({'returncode':p.returncode})+'\n')
  if check:need(p.returncode==0,label+'_failed_no_retry')
  return p.stdout
 def parse(raw):return json.loads(raw)
 admit.main()
 config=json.loads((ROOT/'config.json').read_text())
 need(hashlib.sha256((tmp/'monitor-price-hook.php').read_bytes()).hexdigest()==config['candidate_hook'],'candidate_bundle_mismatch')
 paths={
 'hook':'/var/www/anytoour/data/www/anytoour.ru/data/monitor-price-hook.php',
 'cohort':'/var/www/anytoour/data/www/anytoour.ru/data/published-hotels-monitor-v1.php',
 'collector':'/var/www/anytoour/data/www/anytoour.ru/data/top500-daily-collector-v1.php',
 'journal':'/var/www/anytoour/data/channel-post-operations/monitor-price-after-collection-v1.jsonl'}
 def pins(label):
  raw=ssh('sha256sum '+' '.join(paths.values()),label).decode().splitlines()
  got={line.split()[1]:line.split()[0] for line in raw};need(got=={paths[k]:v for k,v in config['native_before_shas'].items()},'native_before_changed')
  return got
 before=pins('native-before')
 ssh('umask 077; mkdir '+scratch,'scratch')
 for src in [ROOT/'feeds.php',tmp/'monitor-price-hook.php']:
  transfer=subprocess.run(['scp',*opts,str(src),remote+':'+scratch+'/'],capture_output=True);need(transfer.returncode==0,'stage_failed_no_retry')
 feeds=parse(ssh('php '+scratch+'/feeds.php','feeds-before'));need(feeds['ok'] is True,'feeds_before_failed')
 for name,digest in config['feed_shas'].items():need(feeds['files'][name]['sha256']==digest,'feed_preimage_changed')
 admit.main()
 raw=ssh('php '+scratch+'/monitor-price-hook.php --inspect','inspect877',False)
 after=pins('native-after')
 af=parse(ssh('php '+scratch+'/feeds.php','feeds-after'));need(af==feeds,'feeds_changed_during_inspection')
 lines=[l.split(b'=',1)[1] for l in raw.splitlines() if l.startswith(b'MONITOR_POST_FEED_RESULT=')];need(len(lines)==1,'inspect_result_ambiguous')
 result=json.loads(lines[0]);(out/'inspect877-result.json').write_bytes(lines[0]+b'\n')
 expected=set(config['expected_ids']);verified=set(result.get('verified_ids',[]))
 need(result.get('ok') is True and result.get('mode')=='read_only_hook_inspection' and result.get('installed_sha')==admit.APP,'inspection_failed')
 need(result.get('roster_pairs')==877 and result.get('scanned_pairs')==877 and len(verified)==877 and verified==expected,'full877_context_not_verified')
 need(all(result.get(k)==0 for k in ['new_posts','supplier_calls','site_writes','updated_pairs','held_pairs']),'read_only_scope_changed')
 need(not result.get('error_counts') and not result.get('error') and result.get('journal_read')==config['journal_pin'],'read_only_journal_or_errors_changed')
 need(json.loads((out/'inspect877.status.json').read_text())['returncode']==0,'inspect_native_nonzero')
 summary={'ok':True,'mode':'read_only_877_admission','verified_ids':sorted(verified),'eligible_pairs':result.get('eligible_pairs'),'unchanged_pairs':result.get('unchanged_pairs'),'deferred_counts':result.get('deferred_counts'),'old_code_and_journal_unchanged':before==after,'feeds_unchanged':True,'post_writes':0,'feed_writes':0,'supplier_calls':0,'installed_old_hook':config['native_before_shas']['hook'],'candidate_hook':config['candidate_hook'],'run_id':os.environ['GITHUB_RUN_ID']}
 (out/'summary.json').write_text(json.dumps(summary)+'\n')
 admission={'observed_at_epoch':int(time.time()),'native_result_sha256':hashlib.sha256(lines[0]).hexdigest(),'native_result':result,'native_code_and_journal_shas':config['native_before_shas'],'feed_shas':config['feed_shas'],'candidate_hook':config['candidate_hook'],'run_id':os.environ['GITHUB_RUN_ID']}
 (out/'admission.json').write_text(json.dumps(admission,separators=(',',':'))+'\n')
if __name__=='__main__':main()
