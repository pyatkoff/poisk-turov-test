#!/usr/bin/env python3
import fcntl, hashlib, json, os, pathlib, re, sys, types

HERE = pathlib.Path(__file__).resolve().parent
BASE_FILE = HERE / 'hotel_match_anex_user_seen_uae12_direct_v1.py'
EXPECTED_BASE_BLOB = 'fc005a518bcccb19e775526a8123927807fe5b5b'
OP = 'hotel-match-anex-mauritius-bridge12-direct-1971-20260919-v1'
TARGET_IDS = [11745,11751,11754,11756,11758,11761,11770,11775,11788,11789,26841,64582]
KNOWN_PRIOR_FLOOR = 184
REQUIRED_ACCOUNTED_FLOOR = 396
OP_CAP = 450
CONTINUE_CAP = 60
CONTEXT = {
    'departureId': 1, 'countryId': 27,
    'dateFrom': '2026-10-28', 'dateTo': '2026-11-03',
    'nightsFrom': 7, 'nightsTo': 10,
    'adults': 2, 'currency': 'RUB', 'onlyCharter': False,
    'operatorIds': [13], 'hotelIds': TARGET_IDS,
}


def git_blob_sha(path: pathlib.Path) -> str:
    raw = path.read_bytes()
    return hashlib.sha1(b'blob ' + str(len(raw)).encode() + b'\0' + raw).hexdigest()


def load_transformed_base():
    if not BASE_FILE.is_file() or git_blob_sha(BASE_FILE) != EXPECTED_BASE_BLOB:
        raise RuntimeError('pinned_base_mismatch')
    source = BASE_FILE.read_text()
    old = 'for r in range(1,21):'
    new = f'for r in range(1,{CONTINUE_CAP + 1}):'
    if source.count(old) != 1:
        raise RuntimeError('continue_transform_boundary')
    source = source.replace(old, new)
    mod = types.ModuleType('uae12_direct_transformed_bridge12')
    mod.__file__ = str(BASE_FILE)
    exec(compile(source, str(BASE_FILE), 'exec'), mod.__dict__)
    return mod, hashlib.sha256(source.encode()).hexdigest()


def write_locked_compat(f, obj):
    text = json.dumps(obj, ensure_ascii=False, sort_keys=True) + '\n'
    f.seek(0); f.truncate(0)
    if 'b' in getattr(f, 'mode', ''):
        raw = text.encode(); n = f.write(raw); expected = len(raw)
    else:
        n = f.write(text); expected = len(text)
    if n != expected:
        raise RuntimeError('locked_write_short')
    f.flush(); os.fsync(f.fileno())


def patch(mod):
    mod.OP = OP
    mod.TARGET_IDS = list(TARGET_IDS)
    mod.KNOWN_FLOOR = KNOWN_PRIOR_FLOOR
    mod.OP_CAP = OP_CAP
    mod.write_locked = write_locked_compat

    original_reserve = mod.reserve
    def reserve(home, reservation_sha):
        daily_p, _ = mod.quota_paths(home)
        with open(daily_p, 'a+b') as df:
            fcntl.flock(df, fcntl.LOCK_EX)
            daily = mod.load_json_file(df)
            accounted = max(0, int(daily.get('accounted_requests') or 0))
            fcntl.flock(df, fcntl.LOCK_UN)
        if accounted < REQUIRED_ACCOUNTED_FLOOR:
            raise RuntimeError('daily_accounting_floor_regressed')
        q = original_reserve(home, reservation_sha)
        if int(q.get('accounted_before') or 0) < REQUIRED_ACCOUNTED_FLOOR:
            raise RuntimeError('daily_accounting_reservation_regressed')
        q['required_accounted_floor'] = REQUIRED_ACCOUNTED_FLOOR
        return q
    mod.reserve = reserve

    original_http = mod.http_call
    def http_call(token, home, lane, action, path, params=None, **kwargs):
        if action == 'search_start' and path == '/tours/search':
            params = dict(CONTEXT)
        if lane == 'UAE12':
            lane = 'MAURITIUS_BRIDGE12'
        code, body = original_http(token, home, lane, action, path, params, **kwargs)
        if action == 'tour_detail':
            opdir = pathlib.Path(os.environ['MATCH_OPERATION_DIR'])
            tour_id = path.rsplit('/', 1)[-1]
            safe = re.sub(r'[^A-Za-z0-9_.-]', '_', tour_id)[:180]
            mod.durable_write(opdir / ('detail-' + safe + '.json'), {
                'operation': OP,
                'http_status': code,
                'path': path,
                'tour_id': tour_id,
                'response': body,
            })
        return code, body
    mod.http_call = http_call
    return mod


def self_test(mod, transformed_sha):
    assert len(TARGET_IDS) == 12 and len(set(TARGET_IDS)) == 12
    assert mod.OP == OP and mod.TARGET_IDS == TARGET_IDS
    assert mod.KNOWN_FLOOR == 184 and REQUIRED_ACCOUNTED_FLOOR == 396
    assert mod.OP_CAP == 450 and mod.DAILY_LIMIT == 3000
    assert CONTEXT['operatorIds'] == [13] and CONTEXT['countryId'] == 27
    assert CONTEXT['dateFrom'] == '2026-10-28' and CONTEXT['dateTo'] == '2026-11-03'
    assert CONTEXT['nightsFrom'] == 7 and CONTEXT['nightsTo'] == 10
    assert isinstance(transformed_sha, str) and len(transformed_sha) == 64
    assert mod.room_raw({'roomName':'FAMILY DELUXE SEA VIEW ROOM'}) == ['FAMILY DELUXE SEA VIEW ROOM']
    _, ids = mod.link_evidence({'operatorLink':'https://agent.anextour.ru/search/tour?HOTELLIST=5844'})
    assert ids == [5844]
    print(json.dumps({'self_test':'ok','continue_cap':CONTINUE_CAP,'transformed_source_sha256':transformed_sha}))


def main():
    mod, transformed_sha = load_transformed_base()
    mod = patch(mod)
    if '--self-test' in sys.argv:
        self_test(mod, transformed_sha); return 0
    if '--execute' not in sys.argv:
        raise RuntimeError('disabled')
    pathlib.Path(os.environ['MATCH_OPERATION_DIR'], 'transformed-source-sha256.txt').write_text(transformed_sha + '\n')
    return mod.main()


if __name__ == '__main__':
    sys.exit(main())
