"""No retries, replay, provider collection or synthetic nightly identity."""
import base64,gzip,hashlib,json,os,pathlib,re,subprocess,sys
import admit
ROOT=pathlib.Path(__file__).parent
OP='/var/www/anytoour/data/channel-post-operations/price81173-cycle-source-20261118-1'
TARGET='/var/www/anytoour/data/www/anytoour.ru/data/monitor-price-hook.php'
NEW='1ad41a8a3a8d10632e0e6127289481675f655c7f7f9a5bbe39b698c03e73e592'
def need(v,m):
 if not v:raise RuntimeError(m)
def main():
 os.umask(0o077);tmp=pathlib.Path(os.environ['RUNNER_TEMP']);out=tmp/'price-result';out.mkdir(exist_ok=False)
 host=os.environ['DEPLOY_HOST'];user=os.environ['DEPLOY_USER'];need(re.fullmatch(r'[A-Za-z0-9_.:-]+',host) and not host.startswith('-') and re.fullmatch(r'[A-Za-z0-9_-]+',user),'host_invalid')
 opts=['-i',str(tmp/'channel_key'),'-o','BatchMode=yes','-o','IdentitiesOnly=yes','-o','StrictHostKeyChecking=yes','-o','GlobalKnownHostsFile=/dev/null','-o','UserKnownHostsFile='+str(tmp/'channel_known_hosts'),'-o','ConnectTimeout=15','-o','ServerAliveInterval=30','-o','ServerAliveCountMax=6']
 remote=user+'@'+host;scratch='/tmp/price81173-nextpass-'+os.environ['GITHUB_RUN_ID'];need(re.fullmatch(r'/tmp/price81173-nextpass-[0-9]+',scratch),'run_id_invalid')
 def ssh(command,label,check=True):
  p=subprocess.run(['ssh',*opts,remote,'unset GIT_DIR GIT_WORK_TREE GIT_COMMON_DIR GIT_INDEX_FILE GIT_CONFIG_PARAMETERS GIT_CONFIG_COUNT GIT_OBJECT_DIRECTORY GIT_ALTERNATE_OBJECT_DIRECTORIES ANYTOUR_MONITOR_COLLECTION_STARTED_AT ANYTOUR_MONITOR_COLLECTOR_RUN_ID ANYTOUR_MONITOR_HOOK_INSPECT; export GIT_OPTIONAL_LOCKS=0; '+command],capture_output=True)
  (out/(label+'.stdout')).write_bytes(p.stdout);(out/(label+'.status.json')).write_text(json.dumps({'returncode':p.returncode})+'\n')
  if check:need(p.returncode==0,label+'_failed_no_retry')
  return p.stdout
 def parse(raw):return json.loads(raw)
 diagnostics={}
 for path in ['actions/workflows/346454799','actions/permissions']:
  try:
   value=admit.api('poisk-turov-test',path);diagnostics[path]={k:value[k] for k in ['id','name','path','state','created_at','updated_at','enabled','allowed_actions'] if k in value}
  except Exception as error:diagnostics[path]={'read_error_type':type(error).__name__}
 (out/'schedule-diagnostics.json').write_text(json.dumps(diagnostics)+'\n')
 admit.main()
 ssh('umask 077; mkdir '+scratch,'scratch')
 for src in [ROOT/'install.php',ROOT/'feeds.php',tmp/'monitor-price-hook.php']:
  p=subprocess.run(['scp',*opts,str(src),remote+':'+scratch+'/'],capture_output=True);need(p.returncode==0,'stage_failed_no_retry')
 ready=parse(ssh('php '+scratch+'/install.php inspect-new '+scratch+'/monitor-price-hook.php','inspect-start'));need(ready['ok'] is True,'installed_readback_failed');need(ready['journal']['pin']==json.loads((ROOT/'config.json').read_text())['initial_journal_pin'],'initial_journal_changed')
 before=parse(ssh('php '+scratch+'/feeds.php','feeds-start'));need(before['ok'] is True,'feeds_before_not_ok')
 config=json.loads((ROOT/'config.json').read_text());prefix=set(config['prefix_offer_ids']);need(len(prefix)==20,'prefix_scope_invalid')
 need(prefix<=before['files']['hotel-posts-all.xml']['rows'].keys(),'prefix_missing_before_pass')
 for name,digest in config['original_feed_preimages'].items():need(before['files'][name]['sha256']==digest,'original_feed_preimage_changed')
 bundle=(tmp/'monitor-price-hook.php').read_text();encoded=re.search(r'const HOOK_ROSTER_GZIP_B64="([^"]+)"',bundle)[1]
 rows=json.loads(gzip.decompress(base64.b64decode(encoded)))['rows'];bytv={r['tourvisor_hotel_id']:r for r in rows};need(len(bytv)==660,'roster_invalid')
 original_before=before;seen_operations=set(config['previous_operations']);seen_hotels=set(config['previous_scanned_ids']);seen_updated_hotels=set(config['previous_updated_hotel_ids']);initial_scanned=set(seen_hotels);initial_updated=set(seen_updated_hotels);summaries=[]
 # Two new default passes only. Existing per-pass 30/480s policy is unchanged.
 # Each is admitted afresh and gets a unique exclusive marker. No nightly/replay.
 for index in range(1,3):
  admit.main()
  ready=parse(ssh('php '+scratch+'/install.php inspect-new '+scratch+'/monitor-price-hook.php','inspect-prepass-'+str(index)));need(ready['ok'] is True,'prepass_not_clear')
  label='pass-'+str(index)
  # Exactly-once intent; a disconnected native process is UNKNOWN, never repeated.
  command="set -euo pipefail; set -C; printf '%s\\n' '{\"run_id\":\""+os.environ['GITHUB_RUN_ID']+"\",\"mode\":\"default_saved_price_once\"}' > "+OP+"/pass-next-"+os.environ['GITHUB_RUN_ID']+"-"+str(index)+"-started.json; set +C; set +e; php "+TARGET+" > "+OP+"/pass-next-"+os.environ['GITHUB_RUN_ID']+"-"+str(index)+".stdout; status=$?; set -e; cat "+OP+"/pass-next-"+os.environ['GITHUB_RUN_ID']+"-"+str(index)+".stdout; exit $status"
  # Do not impose 480s hard timeout. Hook's original admission budget is unchanged.
  raw=ssh(command,label,False)
  # Always collect read-only outcomes after the one invocation, even nonzero or
  # disconnected/UNKNOWN. Never retry the price hook or infer nonexecution.
  afterraw=ssh('php '+scratch+'/feeds.php','feeds-after-'+str(index),False)
  postraw=ssh('php '+scratch+'/install.php inspect-new '+scratch+'/monitor-price-hook.php','inspect-final-'+str(index),False)
  lines=[l.split(b'=',1)[1] for l in raw.splitlines() if l.startswith(b'MONITOR_POST_FEED_RESULT=')];need(len(lines)==1,'pass_result_ambiguous_unknown')
  result=json.loads(lines[0]);(out/(label+'-result.json')).write_bytes(lines[0]+b'\n')
  after=parse(afterraw);post=parse(postraw)
  need(after['ok'] is True and post['ok'] is True,'final_readback_not_clear')
  bundle=(tmp/'monitor-price-hook.php').read_text();encoded=re.search(r'const HOOK_ROSTER_GZIP_B64="([^"]+)"',bundle)[1]
  rows=json.loads(gzip.decompress(base64.b64decode(encoded)))['rows'];bytv={r['tourvisor_hotel_id']:r for r in rows};need(len(bytv)==660,'roster_invalid')
  updated={x['tourvisor_hotel_id'] for x in result.get('updates',[])};need(updated<=bytv.keys(),'updated_scope_invalid')
  allowed=set()
  for tv in updated:allowed.update(bytv[tv]['feed_binding']['offer_ids'].values())
  config=json.loads((ROOT/'config.json').read_text());prefix=set(config['prefix_offer_ids']);need(len(prefix)==20,'prefix_scope_invalid')
  for name,b in before['files'].items():
   a=after['files'][name];need(list(a['rows'])==list(b['rows']),'feed_members_changed');need(a['envelope']==b['envelope'],'feed_envelope_changed')
   for k,v in b['rows'].items():
    need(a['rows'][k]['stable']==v['stable'],'feed_nonprice_changed')
    if k not in allowed or k in prefix:need(a['rows'][k]['full']==v['full'],'unselected_or_prefix_offer_changed')
  need(prefix<=before['files']['hotel-posts-all.xml']['rows'].keys(),'prefix_missing')
  status=json.loads((out/(label+'.status.json')).read_text())['returncode']
  accepted=status==0 and result.get('ok') is True and result.get('installed_sha')==admit.APP and result.get('roster_pairs')==660 and result.get('budget_seconds')==480 and 0<=result.get('updated_pairs',-1)<=30 and all(result.get(k)==0 for k in ['new_posts','supplier_calls','site_writes']) and not any(result.get(k) for k in ['error','child_failure','pending','preflight_block','error_counts'])
  summary={'ok':accepted,'run_id':os.environ['GITHUB_RUN_ID'],'source':'4fde5c5a8fedcd17882589628a1f466c9e629d4e','installed_hook':NEW,'updated_pairs':result.get('updated_pairs'),'scanned_pairs':result.get('scanned_pairs'),'held_pairs':result.get('held_pairs'),'error_counts':result.get('error_counts'),'remaining_unscanned_pairs':result.get('remaining_unscanned_pairs'),'roster_pairs':660,'feed_counts':[670,670,1340],'tail_prefix10_preserved':True,'pass_index':index,'full_nightly_cycle_claimed':False}
  if not accepted:(out/('summary-'+str(index)+'.json')).write_text(json.dumps(summary)+'\n')
  need(accepted,'pass_not_cleanly_accepted_review_original')
  operations={u['operation'] for u in result.get('updates',[])};need(len(operations)==len(result.get('updates',[]))==result.get('updated_pairs'),'updates_receipt_count_changed');need(not(operations & seen_operations),'operation_repeated');seen_operations|=operations
  scan_ids=updated|{x['tourvisor_hotel_id'] for x in result.get('deferred',[])}|set(result.get('unchanged_ids',[]))|set(result.get('held_ids',[]));summary['new_distinct_hotels']=len(updated-seen_updated_hotels);summary['new_price_operations']=len(operations);summary['new_scanned_ids']=sorted(scan_ids-seen_hotels);seen_updated_hotels.update(updated)
  seen_hotels.update(updated);seen_hotels.update(x['tourvisor_hotel_id'] for x in result.get('deferred',[]));seen_hotels.update(result.get('unchanged_ids',[]));seen_hotels.update(result.get('held_ids',[]));summary['cumulative_unique_scanned']=len(seen_hotels);(out/('summary-'+str(index)+'.json')).write_text(json.dumps(summary)+'\n')
  summaries.append(summary);(out/'continuation-summary.json').write_text(json.dumps({'ok':True,'verified_passes':summaries,'updated_pairs':sum(x['updated_pairs'] for x in summaries),'new_price_operations':sum(x['new_price_operations'] for x in summaries),'new_distinct_hotels':len(seen_updated_hotels-initial_updated),'cumulative_distinct_updated_hotels':len(seen_updated_hotels),'new_scanned_ids':sorted(seen_hotels-initial_scanned),'stopped_complete_scan':result.get('scan_complete') is True})+'\n')
  if result.get('scan_complete') is True:break
  before=after
if __name__=='__main__':main()
