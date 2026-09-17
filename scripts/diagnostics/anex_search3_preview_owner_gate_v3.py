#!/usr/bin/env python3
"""Pure authorization gate for one no-replay ANEX/Andromeda Search3 preview v3 publication."""
from __future__ import annotations

import json
import os
from pathlib import Path
import sys

COMMAND = "/publish-anex-search3-preview-2530-v3"
REPOSITORY = "pyatkoff/poisk-turov-test"
OWNER_LOGIN = "pyatkoff"
OWNER_ID = 226193297
ISSUE_NUMBER = 2530
MAIN_REF = "refs/heads/main"


def authorize(event: dict, env: dict[str, str]) -> tuple[bool, str]:
    if env.get("GITHUB_EVENT_NAME") != "issue_comment":
        return False, "wrong_event"
    if event.get("action") != "created":
        return False, "wrong_action"
    if env.get("GITHUB_REPOSITORY") != REPOSITORY:
        return False, "wrong_repository"
    if env.get("GITHUB_REF") != MAIN_REF:
        return False, "wrong_ref"
    if env.get("GITHUB_RUN_ATTEMPT") != "1":
        return False, "rerun_forbidden"
    if env.get("GITHUB_ACTOR") != OWNER_LOGIN or env.get("GITHUB_TRIGGERING_ACTOR") != OWNER_LOGIN:
        return False, "wrong_actor"

    issue = event.get("issue")
    comment = event.get("comment")
    if not isinstance(issue, dict) or not isinstance(comment, dict):
        return False, "missing_issue_or_comment"
    if issue.get("number") != ISSUE_NUMBER or "pull_request" in issue:
        return False, "wrong_issue"
    user = comment.get("user")
    if not isinstance(user, dict) or user.get("id") != OWNER_ID or user.get("login") != OWNER_LOGIN:
        return False, "wrong_owner"
    if comment.get("body") != COMMAND:
        return False, "wrong_command"
    return True, "authorized"


def main() -> int:
    event_path = Path(os.environ.get("GITHUB_EVENT_PATH", ""))
    try:
        event = json.loads(event_path.read_text(encoding="utf-8"))
    except Exception:
        print("ANEX_PREVIEW_V3_CONTROL_DENIED invalid_event", file=sys.stderr)
        return 1
    allowed, reason = authorize(event, dict(os.environ))
    if not allowed:
        print("ANEX_PREVIEW_V3_CONTROL_DENIED " + reason, file=sys.stderr)
        return 1
    print("ANEX_PREVIEW_V3_CONTROL_AUTHORIZED")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
