#!/usr/bin/env python3
from __future__ import annotations
import json, os, sys
from pathlib import Path
COMMAND='/read-andromeda-first-page-2530-v2'
REPO='pyatkoff/poisk-turov-test'; OWNER='pyatkoff'; OWNER_ID=226193297; ISSUE=2530

def authorize(event, env):
    if env.get('GITHUB_EVENT_NAME')!='issue_comment' or event.get('action')!='created': return False
    if env.get('GITHUB_REPOSITORY')!=REPO or env.get('GITHUB_REF')!='refs/heads/main' or env.get('GITHUB_RUN_ATTEMPT')!='1': return False
    if env.get('GITHUB_ACTOR')!=OWNER or env.get('GITHUB_TRIGGERING_ACTOR')!=OWNER: return False
    issue=event.get('issue'); c=event.get('comment'); u=c.get('user') if isinstance(c,dict) else None
    return isinstance(issue,dict) and issue.get('number')==ISSUE and 'pull_request' not in issue and isinstance(u,dict) and u.get('id')==OWNER_ID and u.get('login')==OWNER and c.get('body')==COMMAND

def main():
    try:event=json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text(encoding='utf-8'))
    except Exception:return 1
    if not authorize(event,dict(os.environ)):
        print('ANDROMEDA_FIRST_PAGE_V2_DENIED',file=sys.stderr);return 1
    print('ANDROMEDA_FIRST_PAGE_V2_AUTHORIZED');return 0
if __name__=='__main__': raise SystemExit(main())
