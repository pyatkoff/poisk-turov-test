#!/usr/bin/env python3
import json,os,sys
from pathlib import Path
COMMAND="/search3-next-live-source-counts-v1"
def authorize(event,env):
    if env.get("GITHUB_EVENT_NAME")!="issue_comment": return False,"wrong_event"
    if env.get("GITHUB_RUN_ATTEMPT")!="1": return False,"rerun_forbidden"
    if env.get("GITHUB_ACTOR")!="pyatkoff" or env.get("GITHUB_TRIGGERING_ACTOR")!="pyatkoff": return False,"wrong_actor"
    issue,comment=event.get("issue"),event.get("comment")
    if not isinstance(issue,dict) or issue.get("number")!=3419 or "pull_request" in issue:return False,"wrong_issue"
    user=comment.get("user") if isinstance(comment,dict) else None
    if not isinstance(user,dict) or user.get("id")!=226193297 or user.get("login")!="pyatkoff":return False,"wrong_owner"
    if comment.get("body")!=COMMAND:return False,"wrong_command"
    return True,"authorized"
if __name__=="__main__":
    try:event=json.loads(Path(os.environ["GITHUB_EVENT_PATH"]).read_text())
    except Exception:sys.exit("SEARCH3_NEXT_COUNTS_DENIED invalid_event")
    ok,reason=authorize(event,dict(os.environ))
    if not ok:sys.exit("SEARCH3_NEXT_COUNTS_DENIED "+reason)
    print("SEARCH3_NEXT_COUNTS_AUTHORIZED")
