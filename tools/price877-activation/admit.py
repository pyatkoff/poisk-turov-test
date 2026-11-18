"""One original bounded saved-price pass; fail closed on changing owners or runtime."""
import datetime,hashlib,json,os,pathlib,subprocess
BASE=pathlib.Path(__file__).parent
APP='cc017ee5bb669f4c7c13efc91d5c5812a50e55f2'
MAIN='f9e3a423990297f7b07014c301eb5e1b18fcec36'
BRANCH='ops/price-hook-reviewed-pass-20261117'
def need(v,why):
 if not v: raise RuntimeError(why)
def api(repo,path):return json.loads(subprocess.check_output(['gh','api','--method','GET','repos/pyatkoff/'+repo+'/'+path],timeout=40))
def main():
 c=json.loads((BASE/'config.json').read_text());e=json.loads(pathlib.Path(os.environ['GITHUB_EVENT_PATH']).read_text());need(c.get('enabled') is True and isinstance(c.get('claim_id'),int) and c['claim_id']>0,'new_claim_not_enabled')
 need(os.environ['GITHUB_REPOSITORY']=='pyatkoff/poisk-turov-test' and os.environ['GITHUB_ACTOR']=='pyatkoff' and os.environ['GITHUB_RUN_ATTEMPT']=='1' and os.environ['GITHUB_EVENT_NAME']=='push' and e['before']=='e7d7c675cf6e52aa5f7326a6101db2ad688c58b4','original_event_required')
 need(api('poisk-turov-test','git/ref/heads/'+BRANCH)['object']['sha']==os.environ['GITHUB_SHA'],'carrier_moved')
 need(api('poisk-turov-test','git/ref/heads/main')['object']['sha']==MAIN,'consumer_main_moved')
 need(api('max-search-bot','git/ref/heads/main')['object']['sha']==APP,'runtime_main_moved')
 need(api('max-search-bot','git/ref/heads/audit/price660-27316-resume-readiness-20261117')['object']['sha']=='1a055d8d5cd94fe0a57a2ee03e41905beff274f9','feed_carrier_moved')
 need(api('max-search-bot','git/ref/heads/audit/content100-bot-publication-20261101')['object']['sha']=='a11f5aa7cf53ea620cc48150e16df00c7e33f9a4','content_carrier_moved')
 r=api('max-search-bot','actions/runs/44215604245');need(r['head_sha']=='bbcf97a198039c894e84644ae9bc1a6064c6ffc7' and r['status']=='completed' and r['conclusion']=='success','p60_not_terminal')
 claim=api('poisk-turov-test','issues/comments/'+str(c['claim_id']));need(claim['user']['id']==226193297 and hashlib.sha256(claim['body'].encode()).hexdigest()==c['claim_sha256'],'claim_changed')
 permit=api('max-search-bot','issues/comments/6957283506');p=json.loads(permit['body']);need(permit['user']['id']==226193297 and p['decision']=='NONE' and p['state']=='COMPLETED_VERIFIED' and p['execution_run_id']==44224540179 and p['verified_pairs']==877 and p['remaining_hotels']==0 and p['previous_native_receipt_sha256']=='78318d0d578714b6073e31eb3eed9999d1d0c7f20ef83dc1f7c1906a5f4ce632','feed_permit_not_closed')
 for rid in [44187866614,44190041893,44190041835,44190042006]:
  r=api('max-search-bot','actions/runs/'+str(rid));need(r['head_sha']==APP and r['run_attempt']==1 and r['status']=='completed' and r['conclusion']=='success','runtime_not_accepted')
 for rid in c['accepted_ci_runs']:
  r=api('poisk-turov-test','actions/runs/'+str(rid));need(r['head_sha']==c['accepted_ci_head'] and r['status']=='completed' and r['conclusion']=='success','source_ci_not_accepted')
 r=api('poisk-turov-test','actions/runs/44217252234');need(r['head_sha']=='7361540b2839029cda2b4f4e2a01fadbdba35bd5' and r['run_attempt']==1 and r['status']=='completed' and r['conclusion']=='success','previous_continuation_not_success')
 for path in ['actions/runs?branch=audit%2Fprice660-27316-resume-readiness-20261117','actions/runs?branch=audit%2Fcontent100-bot-publication-20261101','actions/runs?head_sha='+APP]:
  for status in ['in_progress','queued','waiting','pending','requested']:
   r=api('max-search-bot',path+'&status='+status+'&per_page=100');need(r['total_count']==0 and r['workflow_runs']==[],'competing_max_run')
 # Same-repository collector serialization is held by this job. Cross-repository
 # private route state is freshly attested by the existing coordinator connector.
 now=datetime.datetime.now(datetime.timezone.utc);a=c['route_attestation'];at=datetime.datetime.fromisoformat(a['at'].replace('Z','+00:00'))
 need(a['repo']=='pyatkoff/tour-max' and a['counts']=={s:0 for s in ['in_progress','queued','waiting','pending','requested']} and 0<=(now-at).total_seconds()<=600,'route_attestation_stale_or_busy')
 need(set(c['files'])=={'feeds.php','run.py','admit.py','test_runner.py','paired-install.php','read-journal.php','postpass_verify.py','test_postpass_verify.py'},'file_manifest_incomplete')
 for name,digest in c['files'].items():need(hashlib.sha256((BASE/name).read_bytes()).hexdigest()==digest,'source_changed')
 r=api('poisk-turov-test','actions/runs/44228220710');need(r['head_sha']=='e7d7c675cf6e52aa5f7326a6101db2ad688c58b4' and r['status']=='completed' and r['conclusion']=='success' and r['run_attempt']==1,'full877_readiness_not_accepted')
 print(json.dumps({'ok':True,'at':now.isoformat(),'runtime':APP,'consumer':MAIN,'route_attestation':a,'collector_serialization':'anytour-scheduled-tour-collector'}))
if __name__=='__main__':main()
