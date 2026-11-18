import pathlib,os,subprocess,json,re,hashlib
import admit
P=pathlib.Path(__file__).parent
def need(x,m):
 if not x:raise RuntimeError(m)
def main():
 os.umask(0o077);t=pathlib.Path(os.environ['RUNNER_TEMP']);o=t/'price-result';o.mkdir(exist_ok=False);admit.main();c=json.loads((P/'config.json').read_text())
 host=os.environ['DEPLOY_HOST'];user=os.environ['DEPLOY_USER'];need(re.fullmatch(r'[A-Za-z0-9_.:-]+',host) and not host.startswith('-') and re.fullmatch(r'[A-Za-z0-9_-]+',user),'invalid_host')
 opts=['-i',str(t/'channel_key'),'-o','BatchMode=yes','-o','IdentitiesOnly=yes','-o','StrictHostKeyChecking=yes','-o','GlobalKnownHostsFile=/dev/null','-o','UserKnownHostsFile='+str(t/'channel_known_hosts'),'-o','ConnectTimeout=15'];remote=user+'@'+host;scratch='/tmp/price877-readback-'+os.environ['GITHUB_RUN_ID'];need(re.fullmatch(r'/tmp/price877-readback-[0-9]+',scratch),'invalid_run')
 def ssh(cmd,label):
  r=subprocess.run(['ssh',*opts,remote,'unset GIT_DIR GIT_WORK_TREE GIT_COMMON_DIR GIT_INDEX_FILE; export GIT_OPTIONAL_LOCKS=0; '+cmd],capture_output=True);(o/(label+'.stdout')).write_bytes(r.stdout);(o/(label+'.status.json')).write_text(json.dumps({'returncode':r.returncode}));need(r.returncode==0,label+'_failed');return r.stdout
 paths={'hook':'/var/www/anytoour/data/www/anytoour.ru/data/monitor-price-hook.php','cohort':'/var/www/anytoour/data/www/anytoour.ru/data/published-hotels-monitor-v1.php','collector':'/var/www/anytoour/data/www/anytoour.ru/data/top500-daily-collector-v1.php'}
 def codes(label):
  raw=ssh('sha256sum '+' '.join(paths.values()),label).decode();got={line.split()[1]:line.split()[0] for line in raw.splitlines()};need(got=={paths[k]:v for k,v in c['native_after_shas'].items()},'code_changed');return got
 before=codes('codes-before');need(ssh('git -C /var/www/anytoour/data/www/app.anytoour.ru rev-parse HEAD','runtime-head').decode().strip()==admit.APP,'runtime_changed')
 need(not ssh('git -C /var/www/anytoour/data/www/app.anytoour.ru status --porcelain --untracked-files=no','runtime-dirty').strip(),'runtime_dirty')
 ssh('umask 077; mkdir '+scratch,'scratch')
 for f in [P/'read-journal.php',P/'expected-cycle.json',P/'feeds.php',t/'monitor-price-hook.php']:
  r=subprocess.run(['scp',*opts,str(f),remote+':'+scratch+'/'],capture_output=True);need(r.returncode==0,'stage_failed')
 j=json.loads(ssh('php '+scratch+'/read-journal.php '+scratch+'/monitor-price-hook.php 555175465 bf5fe94d5918ef120ce42cd744681a7937f37cb38049290b59e95230128ed9bb '+scratch+'/expected-cycle.json 9e5a5918c3e04dc99e6a0fc2365d5b46b9f569a250479f805a590f98555eff33','journal'))
 need(j.get('ok') is True and j.get('pending','missing') is None and j.get('writes')==0 and j.get('prefix_verified') is True,'journal_invalid')
 f=json.loads(ssh('php '+scratch+'/feeds.php','feeds'));need(f.get('ok') is True,'feeds_invalid');after=codes('codes-after');admit.main()
 (o/'summary.json').write_text(json.dumps({'ok':True,'mode':'read_only_post_schedule_handoff','run_id':os.environ['GITHUB_RUN_ID'],'source':os.environ['GITHUB_SHA'],'code_hashes':c['native_after_shas'],'journal':j,'feeds':{k:{x:v[x] for x in ['sha256','bytes','public_sha256','public_http']} for k,v in f['files'].items()},'post_writes':0,'feed_writes':0,'supplier_calls':0,'code_writes':0}))
if __name__=='__main__':main()
