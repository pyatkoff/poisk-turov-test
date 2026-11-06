"""Select file delivery or the existing schema bootstrap from a pinned Git diff.

This helper reads Git metadata only. It never contacts a server or a database.
Unknown paths keep the previous schema behavior; incomplete evidence stops delivery.
"""
from __future__ import annotations

import json
import os
from pathlib import Path, PurePosixPath
import re
import subprocess
import sys


FILES_ONLY_PATHS = frozenset({
    '.github/workflows/bootstrap-anytour-data.yml',
    'scripts/deploy/anytour_data_bootstrap_scope.py',
    'tests/anytour_data_bootstrap_delivery_test.py',
    'app/integrations/anex-search-mapping-registry.php',
    'v2/data/top500-anex-priority-v1.php',
    'v2/data/top-hotels-v1.php',
})


class ScopeError(ValueError):
    pass


def need(condition, reason):
    if not condition:
        raise ScopeError(reason)


def checked_sha(value):
    need(isinstance(value, str) and re.fullmatch('[0-9a-f]{40}', value)
         and value != '0' * 40, 'invalid_commit_sha')
    return value


def checked_paths(paths):
    need(isinstance(paths, (list, tuple)), 'invalid_changed_paths')
    for path in paths:
        need(isinstance(path, str) and bool(path) and not path.startswith('/')
             and str(PurePosixPath(path)) == path and '..' not in PurePosixPath(path).parts
             and '\\' not in path and not any(ord(char) < 32 or ord(char) == 127 for char in path),
             'invalid_changed_path')
    need(len(set(paths)) == len(paths), 'duplicate_changed_path')
    return set(paths)


def parse_paths(raw, *, allow_empty=False):
    need(isinstance(raw, bytes), 'invalid_diff_output')
    if not raw:
        need(allow_empty, 'empty_change_set')
        return []
    need(raw.endswith(b'\0'), 'invalid_diff_framing')
    try:
        paths = [part.decode('utf-8', errors='strict') for part in raw[:-1].split(b'\0')]
    except UnicodeDecodeError:
        raise ScopeError('invalid_diff_utf8') from None
    checked_paths(paths)
    return paths


def choose_scope(event_name, changed_paths=(), deleted_paths=()):
    if event_name == 'workflow_dispatch':
        return {'mode': 'schema', 'reason': 'manual_bootstrap', 'changed_paths': 0, 'deleted_paths': 0}
    need(event_name == 'push', 'unsupported_event')
    changed = checked_paths(changed_paths)
    deleted = checked_paths(deleted_paths)
    need(bool(changed), 'empty_change_set')
    need(deleted <= changed, 'inconsistent_deleted_paths')
    files_only = not deleted and changed <= FILES_ONLY_PATHS
    return {'mode': 'files-only' if files_only else 'schema',
            'reason': 'delivery_inputs_only' if files_only else 'schema_or_unknown_input',
            'changed_paths': len(changed), 'deleted_paths': len(deleted)}


def git_read(repo, args, reason):
    try:
        result = subprocess.run(['git', *args], cwd=repo, capture_output=True, timeout=30)
    except (OSError, subprocess.TimeoutExpired):
        raise ScopeError(reason) from None
    need(result.returncode == 0, reason)
    return result.stdout


def inspect_scope(event_name, event, source_sha, repo=Path('.')):
    source_sha = checked_sha(source_sha)
    head = git_read(repo, ['rev-parse', '--verify', 'HEAD'], 'checkout_unavailable').strip()
    need(head == source_sha.encode('ascii'), 'checkout_sha_mismatch')
    if event_name == 'workflow_dispatch':
        return choose_scope(event_name)
    need(event_name == 'push' and isinstance(event, dict), 'unsupported_event')
    before = checked_sha(event.get('before'))
    after = checked_sha(event.get('after'))
    need(after == source_sha, 'event_after_mismatch')
    git_read(repo, ['cat-file', '-e', before + '^{commit}'], 'push_base_unavailable')
    git_read(repo, ['merge-base', '--is-ancestor', before, after], 'push_base_not_ancestor')
    diff = ['diff', '--no-ext-diff', '--no-textconv', '--name-only', '--no-renames', '-z']
    changed = parse_paths(git_read(repo, [*diff, before, after, '--'], 'push_diff_unavailable'))
    deleted = parse_paths(git_read(repo, [*diff, '--diff-filter=D', before, after, '--'],
                                   'push_deletion_diff_unavailable'), allow_empty=True)
    return choose_scope(event_name, changed, deleted)


def main():
    try:
        event = json.loads(Path(os.environ['GITHUB_EVENT_PATH']).read_text())
        output = os.environ['GITHUB_OUTPUT']
        need(bool(output), 'missing_scope_output')
        result = inspect_scope(os.environ.get('GITHUB_EVENT_NAME'), event,
                               os.environ.get('GITHUB_SHA'))
        with Path(output).open('a') as stream:
            stream.write('mode=' + result['mode'] + '\n')
        print('ANYTOUR_DATA_SCOPE ' + json.dumps(result, sort_keys=True))
    except ScopeError as error:
        print('ANYTOUR_DATA_SCOPE_ERROR: ' + str(error), file=sys.stderr)
        return 1
    except (KeyError, OSError, json.JSONDecodeError):
        print('ANYTOUR_DATA_SCOPE_ERROR: event_or_output_unavailable', file=sys.stderr)
        return 1
    return 0


if __name__ == '__main__':
    sys.exit(main())
