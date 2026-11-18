"""One original bounded saved-price pass; fail closed on changing owners or runtime."""
import datetime,hashlib,json,os,pathlib,subprocess
BASE=pathlib.Path(__file__).parent
APP='cc017ee5bb669f4c7c13efc91d5c5812a50e55f2'
MAIN='5ecbb946fbfb6b780daed665e371a490f61a93a2'
BRANCH='ops/price-hook-reviewed-pass-20261117'
def need(v,why):
 if not v: raise RuntimeError(why)
def api(repo,path):return json.loads(subprocess.check_output(['gh','api','--method','GET','repos/pyatkoff/'+repo+'/'+path],timeout=40))
def main():
 c=json.loads((BASE/'config.json').read_text());e=json.loads(pathlib.Path(os.environ['GITHUB_EVENT_PATH']).read_text())
 need(os.environ['GITHUB_REPOSITORY']=='pyatkoff/poisk-turov-test' and os.environ['GITHUB_ACTOR']=='pyatkoff' and os.environ['GITHUB_RUN_ATTEMPT']=='1' and os.environ['GITHUB_EVENT_NAME']=='push' and e['before']=='587ae37baca55c6788e96074a1de71a8540a761b','original_event_required')
 need(api('poisk-turov-test','git/ref/heads/'+BRANCH)['object']['sha']==os.environ['GITHUB_SHA'],'carrier_moved')
 need(api('poisk-turov-test','git/ref/heads/main')['object']['sha']==MAIN,'consumer_main_moved')
 need(api('max-search-bot','git/ref/heads/main')['object']['sha']==APP,'runtime_main_moved')
 need(api('max-search-bot','git/ref/heads/audit/price660-27316-resume-readiness-20261117')['object']['sha']=='567506b5b852b8229b9c2777281df5570c91b5cc','feed_carrier_moved')
 claim=api('poisk-turov-test','issues/comments/6965112328');need(claim['user']['id']==226193297 and hashlib.sha256(claim['body'].encode()).hexdigest()==c['claim_sha256'],'claim_changed')
 permit=api('max-search-bot','issues/comments/6957283506');p=json.loads(permit['body']);need(permit['user']['id']==226193297 and p['write_permission']=='NONE' and p['execution_run_id']==44194475794 and p['writer_started'] is False,'feed_permit_not_closed')
 for rid in [44187866614,44190041893,44190041835,44190042006]:
  r=api('max-search-bot','actions/runs/'+str(rid));need(r['head_sha']==APP and r['run_attempt']==1 and r['status']=='completed' and r['conclusion']=='success','runtime_not_accepted')
 for rid in [44196202554,44196458333]:
  r=api('poisk-turov-test','actions/runs/'+str(rid));need(r['status']=='completed' and r['conclusion']=='success','source_ci_not_accepted')
 for path in ['actions/runs?branch=audit%2Fprice660-27316-resume-readiness-20261117','actions/runs?branch=audit%2Fcontent100-bot-publication-20261101','actions/runs?head_sha='+APP]:
  for status in ['in_progress','queued','waiting','pending','requested']:
   r=api('max-search-bot',path+'&status='+status+'&per_page=100');need(r['total_count']==0 and r['workflow_runs']==[],'competing_max_run')
 # Same-repository collector serialization is held by this job. Cross-repository
 # private route state is freshly attested by the existing coordinator connector.
 now=datetime.datetime.now(datetime.timezone.utc);a=c['route_attestation'];at=datetime.datetime.fromisoformat(a['at'].replace('Z','+00:00'))
 need(a['repo']=='pyatkoff/tour-max' and a['counts']=={s:0 for s in ['in_progress','queued','waiting','pending','requested']} and 0<=(now-at).total_seconds()<=600,'route_attestation_stale_or_busy')
 for name,digest in c['files'].items():need(hashlib.sha256((BASE/name).read_bytes()).hexdigest()==digest,'source_changed')
 print(json.dumps({'ok':True,'at':now.isoformat(),'runtime':APP,'consumer':MAIN,'route_attestation':a,'collector_serialization':'anytour-scheduled-tour-collector'}))
if __name__=='__main__':main()
