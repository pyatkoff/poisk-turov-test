"""Authorize one fresh owner command; duplicates and reruns never reach suppliers."""
import json
import os
from pathlib import Path
import sys
import urllib.request

COMMAND = '/search3-next-live-source-counts-v11'
REPOSITORY = 'pyatkoff/poisk-turov-test'
OWNER = 226193297

def authorize(event, env):
    if env.get('GITHUB_EVENT_NAME') != 'issue_comment' or event.get('action') != 'created': return False, 'wrong_event'
    if env.get('GITHUB_REPOSITORY') != REPOSITORY or env.get('GITHUB_REF') != 'refs/heads/main': return False, 'wrong_repository_or_ref'
    if env.get('GITHUB_RUN_ATTEMPT') != '1': return False, 'rerun_forbidden'
    if env.get('GITHUB_ACTOR') != 'pyatkoff' or env.get('GITHUB_TRIGGERING_ACTOR') != 'pyatkoff': return False, 'wrong_actor'
    issue, comment = event.get('issue'), event.get('comment')
    if not isinstance(issue, dict) or issue.get('number') != 3419 or 'pull_request' in issue: return False, 'wrong_issue'
    if not isinstance(comment, dict): return False, 'wrong_comment'
    user = comment.get('user')
    if not isinstance(user, dict) or user.get('id') != OWNER or user.get('login') != 'pyatkoff' or comment.get('author_association') != 'OWNER': return False, 'wrong_owner'
    if comment.get('body') != COMMAND or type(comment.get('id')) is not int: return False, 'wrong_command'
    return True, 'authorized'

def main():
    try: event = json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text())
    except Exception: return 'invalid_event'
    ok, reason = authorize(event, dict(os.environ))
    if not ok: return reason
    def get(path):
        req = urllib.request.Request('https://api.github.com/repos/' + REPOSITORY + path, headers={'Authorization': 'Bearer ' + os.environ['GH_TOKEN'], 'Accept': 'application/vnd.github+json'})
        with urllib.request.urlopen(req, timeout=20) as r: return json.load(r)
    try:
        fresh = get('/issues/comments/' + str(event['comment']['id']))
        if fresh.get('body') != COMMAND or fresh.get('user', {}).get('id') != OWNER: return 'command_changed'
        found = []
        for page in range(1, 31):
            rows = get('/issues/3419/comments?per_page=100&page=' + str(page))
            found.extend(c['id'] for c in rows if c.get('body') == COMMAND and c.get('user', {}).get('id') == OWNER)
            if len(rows) < 100: break
        else: return 'comment_limit'
        if found != [event['comment']['id']]: return 'duplicate_command'
    except Exception: return 'authorization_read_failed'
    print('SEARCH3_NEXT_COUNTS_AUTHORIZED_V11')
    return None

if __name__ == '__main__':
    reason = main()
    if reason: sys.exit('SEARCH3_NEXT_COUNTS_DENIED ' + reason)
