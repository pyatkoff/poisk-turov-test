"""Shared physical-HTTP budget for new MATCH work; historical runners stay frozen."""
from __future__ import annotations

from contextlib import contextmanager
import datetime as dt
import fcntl
import hashlib
import json
import os
from pathlib import Path
import re
from zoneinfo import ZoneInfo

ACCOUNT = 'TOURVISOR_ANEX_JWT'
DAILY_LIMIT = 5000
POLICY = 'physical-http-v1'
TARIFF_ACTIONS = frozenset({'search_start', 'search_continue', 'flights_actualization'})
READ_ACTIONS = frozenset({'dictionary', 'search_status', 'search_results', 'tour_detail'})


def encoded(value):
    return (json.dumps(value, sort_keys=True, separators=(',', ':')) + '\n').encode()


def checked_count(value):
    if type(value) is not int or value < 0:
        raise ValueError('quota_counter_invalid')
    return value


class Quota:
    def __init__(self, home: Path, clock=None):
        self.home = Path(home)
        self.clock = clock or (lambda: dt.datetime.now(ZoneInfo('Europe/Moscow')))
        self.day = self.clock().astimezone(ZoneInfo('Europe/Moscow')).date().isoformat()
        self.root = self.home / '.anytoour-match/provider-quotas'
        self.path = self.root / ('tourvisor-anex-' + self.day + '.json')

    @contextmanager
    def locked(self, create=False):
        if self.clock().astimezone(ZoneInfo('Europe/Moscow')).date().isoformat() != self.day:
            raise ValueError('quota_provider_day_changed')
        if create:
            self.root.mkdir(parents=True, exist_ok=True, mode=0o700)
        if self.root.is_symlink() or self.root.resolve() != self.root:
            raise ValueError('quota_root_invalid')
        # Stable lock cooperates with selectors; the day-inode lock cooperates
        # with v10/gateway. Never replace the day inode under an older reader.
        lock = self.path.with_suffix('.lock')
        fd = os.open(lock, os.O_RDWR | os.O_CREAT | os.O_NOFOLLOW, 0o600)
        try:
            fcntl.flock(fd, fcntl.LOCK_EX)
            flags = os.O_RDWR | os.O_NOFOLLOW
            created = False
            if create:
                try:
                    dayfd = os.open(self.path, flags | os.O_CREAT | os.O_EXCL, 0o600)
                    created = True
                except FileExistsError:
                    dayfd = os.open(self.path, flags)
            else:
                dayfd = os.open(self.path, flags)
            try:
                fcntl.flock(dayfd, fcntl.LOCK_EX)
                if os.fstat(dayfd).st_nlink != 1 or os.fstat(dayfd).st_size > 8*1024*1024:
                    raise ValueError('quota_day_file_invalid')
                with os.fdopen(os.dup(dayfd), 'r+b') as handle:
                    raw = handle.read()
                    if self.clock().astimezone(ZoneInfo('Europe/Moscow')).date().isoformat() != self.day:
                        raise ValueError('quota_provider_day_changed')
                    if not raw and created:
                        data = {'provider': 'tourvisor-anex', 'provider_day': self.day,
                                'physical_http_attempts': 0, 'tariff_search_units': 0,
                                'operations': {}, 'baseline_status': 'global_usage_not_measured'}
                    else:
                        data = json.loads(raw)
                    if (not isinstance(data, dict) or data.get('provider_day') != self.day
                            or data.get('provider') != 'tourvisor-anex'
                            or not isinstance(data.get('operations'), dict)):
                        raise ValueError('quota_account_day_mismatch')
                    for key in ('physical_http_attempts', 'tariff_search_units'):
                        checked_count(data.get(key))
                    yield handle, data, raw
            finally:
                os.close(dayfd)
        finally:
            os.close(fd)

    @staticmethod
    def write(handle, data):
        raw = encoded(data)
        handle.seek(0)
        handle.write(raw)
        handle.truncate()
        handle.flush()
        os.fsync(handle.fileno())
        handle.seek(0)
        if handle.read() != raw:
            raise ValueError('quota_write_unknown_no_replay')
        return hashlib.sha256(raw).hexdigest()

    def apply_policy(self):
        with self.locked(create=True) as (handle, data, before):
            if data.get('owner_daily_limit') not in (None, 3000, 5000):
                raise ValueError('quota_unreviewed_previous_limit')
            protected = {k: v for k, v in data.items() if k not in
                         ('owner_daily_limit', 'physical_http_daily_limit', 'quota_policy')}
            data.update(owner_daily_limit=DAILY_LIMIT,
                        physical_http_daily_limit=DAILY_LIMIT, quota_policy=POLICY)
            after = self.write(handle, data)
            if protected != {k: v for k, v in data.items() if k not in
                             ('owner_daily_limit', 'physical_http_daily_limit', 'quota_policy')}:
                raise ValueError('quota_protected_state_changed')
            return {'account': ACCOUNT, 'provider_day': self.day, 'policy': POLICY,
                    'daily_physical_http_limit': DAILY_LIMIT,
                    'before_sha256': hashlib.sha256(before).hexdigest(), 'after_sha256': after,
                    'physical_http_attempts': data['physical_http_attempts'],
                    'tariff_search_units': data['tariff_search_units'],
                    'local_remaining_upper_bound': max(0, DAILY_LIMIT-data['physical_http_attempts']),
                    'global_usage': 'not_measured', 'supplier_calls': 0,
                    'operations_preserved': True}

    def reserve(self, operation, request_id, action):
        if (not re.fullmatch(r'int-andromeda-match-[a-z0-9-]{8,80}-v[1-9][0-9]*', operation)
                or type(request_id) is not int or request_id < 1
                or action not in TARIFF_ACTIONS | READ_ACTIONS):
            raise ValueError('quota_request_scope')
        with self.locked() as (handle, data, _):
            if data.get('quota_policy') != POLICY or data.get('physical_http_daily_limit') != DAILY_LIMIT:
                raise ValueError('quota_policy_not_applied')
            calls = data.setdefault('physical_http_reservations', {})
            key = operation + ':' + str(request_id)
            if key in calls:
                raise ValueError('quota_request_no_replay')
            used = checked_count(data['physical_http_attempts'])
            if used >= DAILY_LIMIT:
                raise ValueError('quota_exhausted')
            data['physical_http_attempts'] = used + 1
            charge = int(action in TARIFF_ACTIONS)
            data['tariff_search_units'] += charge
            calls[key] = {'operation_id': operation, 'request_id': request_id,
                          'action': action, 'state': 'reserved_unknown_no_replay',
                          'physical_http_attempts': 1, 'tariff_search_units': charge}
            self.write(handle, data)
            return dict(calls[key])

    def complete(self, operation, request_id, outcome):
        if outcome not in ('response_received', 'failed_unknown_no_replay'):
            raise ValueError('quota_outcome_invalid')
        with self.locked() as (handle, data, _):
            row = data.get('physical_http_reservations', {}).get(operation + ':' + str(request_id))
            if not row or row.get('state') != 'reserved_unknown_no_replay':
                raise ValueError('quota_completion_no_replay')
            row['state'] = outcome
            self.write(handle, data)


if __name__ == '__main__':
    if os.environ.get('MATCH_QUOTA_ACTION') != 'apply-owner-5000':
        raise SystemExit('quota_action_invalid')
    print(json.dumps(Quota(Path(os.environ['HOME'])).apply_policy(), sort_keys=True))
