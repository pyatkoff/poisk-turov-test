"""Exact original event, accepted source and current shared-surface admission."""
import datetime,hashlib,json,os,pathlib,subprocess
BASE=pathlib.Path(__file__).parent
APP='cc017ee5bb669f4c7c13efc91d5c5812a50e55f2'
def need(v,m):
 if not v:raise RuntimeError(m)
def api(repo,path):return json.loads(subprocess.check_output(['gh','api','--method','GET','repos/pyatkoff/'+repo+'/'+path],timeout=40))
def validate_manifest(c):
 files=set(c['files']);expected=set(c['expected_files'])
 if c.get('phase')=='readiness':
  need(files==expected=={'run.py','admit.py','feeds.php'},'incomplete_manifest')
 else:
  need(files==expected and len(files)>=4,'incomplete_manifest')
def main():
 c=json.loads((BASE/'config.json').read_text());e=json.loads(pathlib.Path(os.environ['GITHUB_EVENT_PATH']).read_text());need(c.get('enabled') is True,'gate_closed')
 need(os.environ['GITHUB_REPOSITORY']=='pyatkoff/poisk-turov-test' and os.environ['GITHUB_ACTOR']=='pyatkoff' and os.environ['GITHUB_RUN_ATTEMPT']=='1' and os.environ['GITHUB_EVENT_NAME']=='push' and e['before']==c['event_before'],'original_event_required')
 need(api('poisk-turov-test','git/ref/heads/ops/price-hook-reviewed-pass-20261117')['object']['sha']==os.environ['GITHUB_SHA'],'carrier_moved')
 need(api('poisk-turov-test','git/ref/heads/main')['object']['sha']==c['consumer_main'],'consumer_main_changed')
 for branch,sha in [('main',APP),('audit/price660-27316-resume-readiness-20261117','773076e71ab28f7878bcce33feb8cb47641b69a2'),('audit/content100-bot-publication-20261101','c4fb9b86f233c09f706b4133f5a690300467d7b3')]:need(api('max-search-bot','git/ref/heads/'+branch)['object']['sha']==sha,'shared_surface_changed')
 claim=api('poisk-turov-test','issues/comments/'+str(c['claim_id']));need(claim['user']['id']==226193297 and hashlib.sha256(claim['body'].encode()).hexdigest()==c['claim_sha256'],'claim_changed')
 q=api('max-search-bot','issues/comments/6972314498');p=json.loads(q['body']);need(q['user']['id']==226193297 and p['decision']=='NONE' and p['execution_run_id']==44262520865 and p['execution_head_sha']=='773076e71ab28f7878bcce33feb8cb47641b69a2' and p['pairs']==1000 and p['completed_batches']==list(range(28,42)) and p['final_native_receipt_sha256']=='15ba5df18d748b6ce3751174963ce9ac8ec32433720a4b9666c8cb37ab85f297','feed_permit_changed')
 for repo,rid,head,conclusion in [('max-search-bot',44262520865,'773076e71ab28f7878bcce33feb8cb47641b69a2','success'),('max-search-bot',44255962195,'c4fb9b86f233c09f706b4133f5a690300467d7b3','success'),('poisk-turov-test',44254140999,'f767095344e5268c5ddfd26c71358a43b71bbaf4','success'),('poisk-turov-test',44236272559,'f9e3a423990297f7b07014c301eb5e1b18fcec36','failure')]:
  r=api(repo,'actions/runs/'+str(rid));need(r['head_sha']==head and r['run_attempt']==1 and r['status']=='completed' and r['conclusion']==conclusion,'original_terminal_changed')
 need(c['phase']=='readiness' or len(c['accepted_ci_runs'])>=3,'ci_missing')
 for rid in c['accepted_ci_runs']:
  r=api('poisk-turov-test','actions/runs/'+str(rid));need(r['head_sha']==c['accepted_ci_head'] and r['status']=='completed' and r['conclusion']=='success','CI_not_accepted')
 for path in ['actions/runs?branch=audit%2Fprice660-27316-resume-readiness-20261117','actions/runs?branch=audit%2Fcontent100-bot-publication-20261101','actions/runs?head_sha='+APP]:
  for status in ['in_progress','queued','waiting','pending','requested']:
   r=api('max-search-bot',path+'&status='+status+'&per_page=100');need(r['total_count']==0 and r['workflow_runs']==[],'competing_native_run')
 now=datetime.datetime.now(datetime.timezone.utc);a=c['route_attestation'];at=datetime.datetime.fromisoformat(a['at'].replace('Z','+00:00'));need(a['repo']=='pyatkoff/tour-max' and a['head']=='a65e667241ce3e910d33a50b3f3e2ce1bc9f66a0' and a['counts']=={s:0 for s in ['in_progress','queued','waiting','pending','requested']} and 0<=(now-at).total_seconds()<=600,'route_attestation_stale_or_busy')
 validate_manifest(c)
 for n,d in c['files'].items():need(hashlib.sha256((BASE/n).read_bytes()).hexdigest()==d,'source_changed')
 print(json.dumps({'ok':True,'at':now.isoformat(),'runtime':APP,'consumer_main':c['consumer_main'],'event_before':c['event_before'],'route':a,'concurrency':'anytour-scheduled-tour-collector'}))
if __name__=='__main__':main()
