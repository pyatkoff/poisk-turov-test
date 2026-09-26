"""Authorize one fresh direct-ANEX Search3 probe."""
import json, os, sys, urllib.request
from pathlib import Path
COMMAND='/search3-direct-anex-live-probe-v2'
REPOSITORY='pyatkoff/poisk-turov-test'
OWNER=226193297
def authorize(event,env):
    if env.get('GITHUB_EVENT_NAME')!='issue_comment' or event.get('action')!='created': return False,'wrong_event'
    if env.get('GITHUB_RUN_ATTEMPT')!='1' or env.get('GITHUB_REPOSITORY')!=REPOSITORY or env.get('GITHUB_REF')!='refs/heads/main': return False,'wrong_run'
    if env.get('GITHUB_ACTOR')!='pyatkoff' or env.get('GITHUB_TRIGGERING_ACTOR')!='pyatkoff': return False,'wrong_actor'
    issue,comment=event.get('issue'),event.get('comment')
    if not isinstance(issue,dict) or issue.get('number')!=3419 or 'pull_request' in issue:return False,'wrong_issue'
    user=comment.get('user') if isinstance(comment,dict) else None
    if not isinstance(user,dict) or user.get('id')!=OWNER or user.get('login')!='pyatkoff' or comment.get('author_association')!='OWNER':return False,'wrong_owner'
    if comment.get('body')!=COMMAND:return False,'wrong_command'
    return True,'authorized'
def main():
    event=json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text())
    ok,reason=authorize(event,dict(os.environ))
    if not ok: raise SystemExit('SEARCH3_DIRECT_ANEX_PROBE_DENIED '+reason)
    token=os.environ.get('GH_TOKEN',''); trigger=event['comment']['id']; page=1;matches=[]
    while True:
        req=urllib.request.Request(f'https://api.github.com/repos/{REPOSITORY}/issues/3419/comments?per_page=100&page={page}',headers={'Authorization':'Bearer '+token,'Accept':'application/vnd.github+json'})
        with urllib.request.urlopen(req,timeout=20) as r: rows=json.load(r)
        matches.extend(x for x in rows if x.get('body')==COMMAND and x.get('user',{}).get('id')==OWNER)
        if len(rows)<100: break
        page+=1
        if page>30: raise SystemExit('comment_scan_limit')
    if len(matches)!=1 or matches[0].get('id')!=trigger: raise SystemExit('duplicate_or_stale_owner_command')
    print('SEARCH3_DIRECT_ANEX_PROBE_AUTHORIZED')
if __name__=='__main__': main()
