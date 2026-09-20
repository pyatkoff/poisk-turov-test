#!/usr/bin/env python3
"""Read existing MATCH quota files only. Never initialize quota or authorize HTTP."""
import datetime as dt
import fcntl
import hashlib
import json
import os
from pathlib import Path
import re
import stat
import sys
import tempfile
from zoneinfo import ZoneInfo

DAY = '2026-09-20'
OP = 'hotel-match-tourvisor-day-ledger-audit-1971-20260920-v1'
MAX_FILES = 5000
MAX_BYTES = 4 * 1024 * 1024
COUNTERS = ('owner_daily_limit', 'accounted_requests', 'known_prior_attempt_floor',
            'match_new_attempts', 'used', 'operation_cap')

def read_one(path):
    fd = os.open(path, os.O_RDONLY | os.O_NOFOLLOW)
    try:
        fcntl.flock(fd, fcntl.LOCK_SH | fcntl.LOCK_NB)
        before = os.fstat(fd)
        if not stat.S_ISREG(before.st_mode) or before.st_size > MAX_BYTES:
            raise ValueError('ledger_file_bounds')
        with os.fdopen(os.dup(fd), 'rb') as stream:
            raw = stream.read(MAX_BYTES + 1)
        after = os.fstat(fd)
        if len(raw) != before.st_size or (before.st_size, before.st_mtime_ns) != (after.st_size, after.st_mtime_ns):
            raise ValueError('ledger_changed_during_read')
        obj = json.loads(raw)
        if not isinstance(obj, dict):
            raise ValueError('ledger_shape')
        counters = {}
        for key in COUNTERS:
            if key in obj:
                value = obj[key]
                if type(value) is not int or value < 0:
                    raise ValueError('ledger_counter_invalid')
                counters[key] = value
        day = obj.get('provider_day')
        if day is not None and (not isinstance(day, str) or not re.fullmatch(r'20\d\d-\d\d-\d\d', day)):
            raise ValueError('ledger_day_invalid')
        # Only bounded operation states and known numeric quota fields leave the server.
        status = obj.get('status', obj.get('state'))
        if status is not None and (not isinstance(status, str) or not re.fullmatch(r'[a-z_]{1,80}', status)):
            status = 'other_state_withheld'
        return {'file': path.name, 'sha256': hashlib.sha256(raw).hexdigest(),
                'provider_day': day, 'state': status, 'counters': counters}
    finally:
        os.close(fd)

def audit(home, day):
    dt.date.fromisoformat(day)
    directory = Path(home) / '.anytour-match/provider-quotas'
    result = {'operation_id': OP, 'provider_day': day,
              'audited_at_utc': dt.datetime.now(dt.timezone.utc).isoformat(),
              'read_only': True, 'provider_calls': 0, 'database_reads': 0,
              'database_writes': 0, 'quota_writes': 0, 'mapping_writes': 0,
              'safe_to_initialize_day': False, 'safe_to_query_now': False,
              'account_usage_authority': 'not_established_by_filesystem_inventory'}
    if directory.is_symlink():
        raise ValueError('quota_directory_symlink')
    if not directory.exists():
        return result | {'state': 'directory_absent', 'daily_ledger_present': False}
    if not directory.is_dir() or directory.resolve() != directory.absolute():
        raise ValueError('quota_directory_invalid')
    paths = sorted(directory.glob('tourvisor-*.json'))
    if len(paths) > MAX_FILES:
        raise ValueError('quota_directory_bounds')
    rows = []
    for path in paths:
        if not re.fullmatch(r'tourvisor-[a-zA-Z0-9._-]{1,240}\.json', path.name):
            raise ValueError('ledger_filename_invalid')
        rows.append(read_one(path))
    target = 'tourvisor-test-' + day + '.json'
    current = [x for x in rows if x['file'] == target]
    operations = [x for x in rows if not re.fullmatch(r'tourvisor-test-20\d\d-\d\d-\d\d\.json', x['file'])]
    current_ops = [x for x in operations if x['provider_day'] == day or ('-' + day.replace('-', '') + '-') in x['file']]
    unknown_ops = [x for x in operations if x['provider_day'] is None and not re.search(r'-20\d{6}-', x['file'])]
    daily = [x for x in rows if re.fullmatch(r'tourvisor-test-20\d\d-\d\d-\d\d\.json', x['file'])]
    # Missing counters remain unknown. This sum is diagnostic, never available balance.
    result.update(state='completed_read_only', scanned_files=len(rows),
                  daily_ledger_present=bool(current), current_daily_ledger=current,
                  current_operation_count=len(current_ops), current_operations=current_ops,
                  unknown_day_operation_count=len(unknown_ops), unknown_day_operations=unknown_ops,
                  retained_daily_ledgers=daily,
                  snapshot_files_sha256=hashlib.sha256(json.dumps(rows, sort_keys=True).encode()).hexdigest())
    return result

def selftest():
    tests = 0
    with tempfile.TemporaryDirectory() as tmp:
        home = Path(tmp)
        assert audit(home, DAY)['state'] == 'directory_absent'; tests += 1
        directory = home / '.anytour-match/provider-quotas'; directory.mkdir(parents=True)
        p = directory / 'tourvisor-test-2026-09-19.json'
        p.write_text(json.dumps({'accounted_requests': 1262, 'owner_daily_limit': 3000, 'token': 'DO_NOT_EMIT'}))
        before = p.read_bytes()
        r = audit(home, DAY)
        assert not r['daily_ledger_present'] and r['current_operation_count'] == 0; tests += 1
        assert 'DO_NOT_EMIT' not in json.dumps(r) and p.read_bytes() == before; tests += 1
        p2 = directory / ('tourvisor-' + OP + '.json')
        p2.write_text(json.dumps({'provider_day': DAY, 'used': 7, 'status': 'provider_accessed'}))
        r = audit(home, DAY)
        assert r['current_operation_count'] == 1 and not r['safe_to_initialize_day']; tests += 1
        p3 = directory / ('tourvisor-test-' + DAY + '.json')
        p3.write_text('{"accounted_requests": 9, "owner_daily_limit": 3000}')
        assert audit(home, DAY)['daily_ledger_present']; tests += 1
        p3.write_text('{"accounted_requests": true}')
        try: audit(home, DAY)
        except ValueError as e: assert str(e) == 'ledger_counter_invalid'
        else: raise AssertionError('boolean accepted as quota')
        tests += 1
        p3.unlink(); p3.symlink_to(p)
        try: audit(home, DAY)
        except OSError: pass
        else: raise AssertionError('symlink followed')
        tests += 1
        p3.unlink()
        with p.open('rb') as locked:
            fcntl.flock(locked, fcntl.LOCK_EX | fcntl.LOCK_NB)
            try: audit(home, DAY)
            except BlockingIOError: pass
            else: raise AssertionError('busy ledger read')
        tests += 1
    print('MATCH_DAY_LEDGER_READ_ONLY_TESTS_OK', tests)

if __name__ == '__main__':
    if sys.argv[1:] == ['--self-test']:
        selftest()
    elif sys.argv[1:] == ['--audit']:
        if dt.datetime.now(ZoneInfo('Europe/Moscow')).date().isoformat() != DAY:
            raise SystemExit('provider_day_mismatch')
        print(json.dumps(audit(Path.home(), DAY), ensure_ascii=False, sort_keys=True, indent=2))
    else:
        raise SystemExit('disabled')
