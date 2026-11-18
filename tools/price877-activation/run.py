"""No retries, replay, provider collection or synthetic nightly identity."""
import base64,gzip,hashlib,json,os,pathlib,re,subprocess,sys,time
import admit
import postpass_verify
ROOT=pathlib.Path(__file__).parent
def need(v,m):
 if not v:raise RuntimeError(m)
def main():
 os.umask(0o077);tmp=pathlib.Path(os.environ['RUNNER_TEMP']);out=tmp/'price-result';out.mkdir(exist_ok=False)
 host=os.environ['DEPLOY_HOST'];user=os.environ['DEPLOY_USER'];need(re.fullmatch(r'[A-Za-z0-9_.:-]+',host) and not host.startswith('-') and re.fullmatch(r'[A-Za-z0-9_-]+',user),'host_invalid')
 opts=['-i',str(tmp/'channel_key'),'-o','BatchMode=yes','-o','IdentitiesOnly=yes','-o','StrictHostKeyChecking=yes','-o','GlobalKnownHostsFile=/dev/null','-o','UserKnownHostsFile='+str(tmp/'channel_known_hosts'),'-o','ConnectTimeout=15','-o','ServerAliveInterval=30','-o','ServerAliveCountMax=6']
 remote=user+'@'+host;scratch='/tmp/price877-activation-'+os.environ['GITHUB_RUN_ID'];need(re.fullmatch(r'/tmp/price877-activation-[0-9]+',scratch),'run_id_invalid')
 def ssh(command,label,check=True):
  try:
   p=subprocess.run(['ssh',*opts,remote,'unset GIT_DIR GIT_WORK_TREE GIT_COMMON_DIR GIT_INDEX_FILE GIT_CONFIG_PARAMETERS GIT_CONFIG_COUNT GIT_OBJECT_DIRECTORY GIT_ALTERNATE_OBJECT_DIRECTORIES ANYTOUR_MONITOR_COLLECTION_STARTED_AT ANYTOUR_MONITOR_COLLECTOR_RUN_ID ANYTOUR_MONITOR_HOOK_INSPECT; export GIT_OPTIONAL_LOCKS=0; '+command],capture_output=True)
  except Exception as error:
   (out/(label+'.stdout')).write_bytes(b'');(out/(label+'.status.json')).write_text(json.dumps({'returncode':None,'outcome':'UNKNOWN','error_type':type(error).__name__})+'\n')
   if check:raise RuntimeError(label+'_transport_unknown_no_retry') from None
   return b''
  (out/(label+'.stdout')).write_bytes(p.stdout);(out/(label+'.status.json')).write_text(json.dumps({'returncode':p.returncode})+'\n')
  if check:need(p.returncode==0,label+'_failed_no_retry')
  return p.stdout
 def parse(raw):return json.loads(raw)
 def stage(src,label):
  p=subprocess.run(['scp',*opts,str(src),remote+':'+scratch+'/'],capture_output=True)
  (out/(label+'.status.json')).write_text(json.dumps({'returncode':p.returncode})+'\n')
  need(p.returncode==0,'stage_failed_no_retry')
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
 for src in [ROOT/'feeds.php',ROOT/'read-journal.php',tmp/'monitor-price-hook.php',ROOT.parents[1]/'price877-consumer/v2/data/published-hotels-monitor-v1.php',ROOT.parents[1]/'price877-consumer/v2/data/top500-daily-collector-v1.php']:
  stage(src,'stage-'+src.name)
 feeds=parse(ssh('php '+scratch+'/feeds.php','feeds-before'));need(feeds['ok'] is True,'feeds_before_failed')
 for name,digest in config['feed_shas'].items():need(feeds['files'][name]['sha256']==digest,'feed_preimage_changed')
 admit.main()
 raw=ssh('php '+scratch+'/monitor-price-hook.php --inspect','inspect877',False)
 inspect_completed_at=int(time.time())  # Before every post-inspection pin/feed check.
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
 (out/'readiness-summary.json').write_text(json.dumps(summary)+'\n')
 admission={'observed_at_epoch':inspect_completed_at,'native_result_sha256':hashlib.sha256(lines[0]).hexdigest(),'native_result':result,'native_code_and_journal_shas':config['native_before_shas'],'feed_shas':config['feed_shas'],'candidate_hook':config['candidate_hook'],'run_id':os.environ['GITHUB_RUN_ID']}
 (out/'admission.json').write_text(json.dumps(admission,separators=(',',':'))+'\n')
 # Bind only the reviewed three literal placeholders to this exact fresh result.
 original=(ROOT/'paired-install.php').read_text()
 need(hashlib.sha256(original.encode()).hexdigest()==config['installer_template_sha256'],'installer_template_changed')
 edits={"const ACTIVATION_ENABLED=false;":"const ACTIVATION_ENABLED=true;",
        "const EXPECTED_NATIVE_RECEIPT_SHA='UNSET';":"const EXPECTED_NATIVE_RECEIPT_SHA='"+hashlib.sha256((out/'admission.json').read_bytes()).hexdigest()+"';",
        "const EXPECTED_NATIVE_RESULT_SHA='UNSET';":"const EXPECTED_NATIVE_RESULT_SHA='"+hashlib.sha256(lines[0]).hexdigest()+"';"}
 bound=bind_installer(original,edits)
 (tmp/'paired-install.php').write_text(bound)
 (out/'installer-binding.json').write_text(json.dumps({'template_sha256':config['installer_template_sha256'],'bound_sha256':hashlib.sha256(bound.encode()).hexdigest(),'admission_sha256':hashlib.sha256((out/'admission.json').read_bytes()).hexdigest(),'native_result_sha256':hashlib.sha256(lines[0]).hexdigest()})+'\n')
 stage(tmp/'paired-install.php','stage-bound-installer');stage(out/'admission.json','stage-admission')
 args=' '+scratch+'/monitor-price-hook.php '+scratch+'/published-hotels-monitor-v1.php '+scratch+'/top500-daily-collector-v1.php '+scratch+'/admission.json'
 admit.main()
 oldraw=ssh('php '+scratch+'/paired-install.php inspect-old'+args,'paired-inspect-old');old=parse(oldraw)
 need(old.get('ok') is True and old.get('mode')=='inspect-old','paired_old_not_ready')
 (out/'inspect-old-result.json').write_bytes(oldraw);stage(out/'inspect-old-result.json','stage-baseline')
 # Exactly one installation invocation. A nonzero or disconnect is UNKNOWN; never retry.
 installraw=ssh('php '+scratch+'/paired-install.php install'+args+' '+scratch+'/inspect-old-result.json','paired-install',False)
 newraw=ssh('php '+scratch+'/paired-install.php inspect-new'+args,'paired-inspect-new',False)
 install=parse(installraw);installed=parse(newraw)
 for label,value in [('paired-install',install),('paired-inspect-new',installed)]:
  need(json.loads((out/(label+'.status.json')).read_text())['returncode']==0 and value.get('ok') is True,'installation_not_verified_no_pass')
 need(install.get('code_replacements')==1 and install.get('consumer_code_writes')==0 and install.get('journal_unchanged') is True,'installation_incomplete')
 postinstallfeeds=parse(ssh('php '+scratch+'/feeds.php','feeds-after-install'))
 need(postinstallfeeds==feeds,'installation_changed_feeds')
 admit.main()
 op='/var/www/anytoour/data/channel-post-operations/price877-hook-only-20261118-1'
 command=price_command(op,os.environ['GITHUB_RUN_ID'])
 passraw=ssh(command,'price-pass',False)
 # Unconditional readbacks, including after nonzero/SSH UNKNOWN. No writer repeat.
 journalraw=ssh('php '+scratch+'/read-journal.php '+scratch+'/monitor-price-hook.php '+str(config['journal_pin']['bytes'])+' '+config['journal_pin']['sha256'],'journal-after-pass',False)
 afterraw=ssh('php '+scratch+'/feeds.php','feeds-after-pass',False)
 postcodes=ssh('sha256sum '+' '.join([paths[k] for k in ['hook','cohort','collector']]),'code-after-pass',False)
 need(parse_code_hashes(postcodes)==config['native_after_shas'],'installed_code_changed')
 results=[l.split(b'=',1)[1] for l in passraw.splitlines() if l.startswith(b'MONITOR_POST_FEED_RESULT=')]
 need(len(results)==1,'price_result_unknown')
 price=json.loads(results[0]);(out/'price-result.json').write_bytes(results[0]+b'\n')
 for label in ['price-pass','journal-after-pass','feeds-after-pass','code-after-pass']:
  need(json.loads((out/(label+'.status.json')).read_text())['returncode']==0,'postpass_not_verified')
 encoded=re.search(r'const HOOK_ROSTER_GZIP_B64="([^"]+)"',(tmp/'monitor-price-hook.php').read_text())[1]
 rows=json.loads(gzip.decompress(base64.b64decode(encoded)))['rows']
 summary=postpass_verify.verify(price,feeds,parse(afterraw),parse(journalraw),config,rows)
 need(summary.get('ok') is True,'postpass_not_verified')
 (out/'summary.json').write_text(json.dumps(summary)+'\n')
def parse_code_hashes(raw):
 names={'monitor-price-hook.php':'hook','published-hotels-monitor-v1.php':'cohort','top500-daily-collector-v1.php':'collector'}
 got={}
 for line in raw.decode().splitlines():
  digest,path=line.split();name=path.rsplit('/',1)[-1];need(name in names and names[name] not in got and re.fullmatch('[a-f0-9]{64}',digest),'code_pin_parse_failed');got[names[name]]=digest
 return got
def bind_installer(original,edits):
 for old,new in edits.items():need(original.count(old)==1,'installer_binding_ambiguous')
 bound=original
 for old,new in edits.items():bound=bound.replace(old,new,1)
 restored=bound
 for old,new in edits.items():restored=restored.replace(new,old,1)
 need(restored==original,'installer_binding_scope_changed')
 return bound
def price_command(op,run_id):
 need(re.fullmatch('[0-9]+',run_id),'run_id_invalid')
 marker=op+'/pass877-'+run_id
 return "set -euo pipefail; set -C; printf '%s\\n' 'default_saved_price_once' > "+marker+"-started.json; set +C; set +e; php /var/www/anytoour/data/www/anytoour.ru/data/monitor-price-hook.php > "+marker+".stdout; status=$?; set -e; cat "+marker+".stdout; exit $status"
if __name__=='__main__':main()
